<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\controllers;

use humhub\modules\space\models\Space;
use humhub\modules\thiscoveryIncentives\models\Payment;
use humhub\modules\thiscoveryIncentives\models\PaymentEvent;
use humhub\modules\thiscoveryIncentives\models\Reward;
use humhub\modules\thiscoveryIncentives\models\RewardForm;
use humhub\modules\thiscoveryIncentives\services\Access;
use humhub\modules\thiscoveryIncentives\services\AuthoriseService;
use humhub\modules\thiscoveryIncentives\services\AydaClient;
use humhub\modules\thiscoveryIncentives\services\AydaException;
use humhub\modules\thiscoveryIncentives\services\ExportService;
use humhub\modules\thiscoveryIncentives\services\Ledger;
use humhub\modules\thiscoveryIncentives\services\ListBuilder;
use humhub\modules\thiscoveryIncentives\services\Money;
use humhub\modules\thiscoveryIncentives\services\RegisterQuery;
use humhub\modules\user\models\Group;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\web\NotFoundHttpException;

trait ManageTrait
{
    abstract protected function scopeSpace(): ?Space;

    public static function verbConfig(array $behaviors): array
    {
        $behaviors['verbs'] = [
            'class' => VerbFilter::class,
            'actions' => [
                'save-list' => ['POST'],
                'refresh' => ['POST'],
                'confirm' => ['POST'],
                'authorise' => ['POST'],
                'close' => ['POST'],
                'approve' => ['POST'],
                'cancel' => ['POST'],
                'export' => ['POST'],
                'update' => ['POST'],
            ],
        ];
        return $behaviors;
    }

    public function actionIndex()
    {
        $this->guard();
        $query = Reward::find()->orderBy(['id' => SORT_DESC]);
        $space = $this->scopeSpace();
        if ($space) {
            $query->andWhere(['space_id' => (int)$space->id]);
        }
        return $this->render('@thiscovery-incentives/views/manage/index', [
            'rewards' => $query->all(),
            'space' => $space,
        ]);
    }

    public function actionCreate()
    {
        $this->guard();
        $model = new RewardForm();
        $model->fixedSpace = $this->scopeSpace();
        $model->source = Yii::$app->request->get('source', $model->fixedSpace ? Reward::SOURCE_COMMUNITY : Reward::SOURCE_COMMUNITY);
        if ($model->fixedSpace) {
            $model->audience = Reward::AUDIENCE_SPACE;
            $model->space_id = (int)$model->fixedSpace->id;
        }
        $pickedForm = (int)Yii::$app->request->get('formId', 0);
        if ($pickedForm && $model->source === Reward::SOURCE_FORM) {
            $model->form_id = $pickedForm;
        }
        if ($model->load(Yii::$app->request->post())) {
            if (Yii::$app->request->post('save')) {
                if ($model->validate()) {
                    $reward = new Reward();
                    $model->toReward($reward);
                    $now = date('Y-m-d H:i:s');
                    $reward->status = Reward::STATUS_OPEN;
                    $reward->created_by = Yii::$app->user->id;
                    $reward->created_at = $now;
                    $reward->updated_at = $now;
                    $reward->save(false);
                    $this->view->success('Reward saved. Add funds to the Ayda ' . $reward->currency . ' wallet before anyone can be paid. Refresh the list to add people.');
                    return $this->redirect($this->route('view', ['id' => $reward->id]));
                }
                $this->view->error(implode(' ', $model->getErrorSummary(true)));
            }
        }
        return $this->render('@thiscovery-incentives/views/manage/create', [
            'model' => $model,
            'space' => $this->scopeSpace(),
            'forms' => RewardForm::formOptions($this->scopeSpace()),
            'groups' => ArrayHelper::map(Group::find()->orderBy(['name' => SORT_ASC])->all(), 'id', 'name'),
            'spaces' => $this->scopeSpace() ? [] : ArrayHelper::map(Space::find()->orderBy(['name' => SORT_ASC])->all(), 'id', 'name'),
        ]);
    }

    public function actionView($id)
    {
        $this->guard();
        $reward = $this->findReward($id);
        \humhub\modules\thiscoveryIncentives\services\ResponseRemoval::sweep($reward);
        $query = Payment::find()->where(['reward_id' => (int)$reward->id])->orderBy(['id' => SORT_ASC]);
        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 50],
        ]);
        return $this->render('@thiscovery-incentives/views/manage/view', [
            'reward' => $reward,
            'provider' => $provider,
            'space' => $this->scopeSpace(),
        ]);
    }

    public function actionEdit($id)
    {
        $this->guard();
        $reward = $this->findReward($id);
        $model = new RewardForm();
        $model->fixedSpace = $this->scopeSpace();
        $model->fromReward($reward);
        $requestedForm = (int)Yii::$app->request->get('formId', 0);
        if ($requestedForm && !$model->formLocked) {
            $model->form_id = $requestedForm;
            $model->source = Reward::SOURCE_FORM;
        }
        $requestedSource = (string)Yii::$app->request->get('source', '');
        if (!$model->formLocked && in_array($requestedSource, [Reward::SOURCE_FORM, Reward::SOURCE_COMMUNITY], true) && !$requestedForm) {
            $model->source = $requestedSource;
        }
        if ($model->load(Yii::$app->request->post())) {
            $model->rewardId = (int)$reward->id;
            $model->currencyLocked = trim((string)$reward->ayda_project_id) !== '';
            $model->formLocked = Payment::find()->where(['reward_id' => (int)$reward->id])->exists();
            if ($model->currencyLocked) {
                $model->currency = $reward->currency;
            }
            if ($model->formLocked) {
                $model->form_id = $reward->form_id;
                $model->source = $reward->source;
            }
            if ($model->validate()) {
                $model->toReward($reward);
                $reward->updated_at = date('Y-m-d H:i:s');
                $reward->save(false);
                $this->view->success('Reward updated. People already on the list stay there and keep their amounts. Refresh adds anyone the new rule now includes, and updates names and emails that have not been sent.');
                return $this->redirect($this->route('view', ['id' => $reward->id]));
            }
            $this->view->error(implode(' ', $model->getErrorSummary(true)));
        }
        return $this->render('@thiscovery-incentives/views/manage/create', [
            'model' => $model,
            'space' => $this->scopeSpace(),
            'forms' => RewardForm::formOptions($this->scopeSpace(), $model->form_id ? (int)$model->form_id : null),
            'groups' => ArrayHelper::map(Group::find()->orderBy(['name' => SORT_ASC])->all(), 'id', 'name'),
            'spaces' => $this->scopeSpace() ? [] : ArrayHelper::map(Space::find()->orderBy(['name' => SORT_ASC])->all(), 'id', 'name'),
            'reward' => $reward,
        ]);
    }

    public function actionUpdate($id)
    {
        $this->guard();
        $reward = $this->findReward($id);
        $sent = Payment::find()->where(['reward_id' => (int)$reward->id])->andWhere(['not', ['authorised_at' => null]])->exists();
        if ($sent) {
            $this->view->error('The amount can no longer be changed for the whole reward, because payment has already been sent. Change a person on the list instead.');
            return $this->redirect($this->route('view', ['id' => $reward->id]));
        }
        $amount = Yii::$app->request->post('default_amount');
        $title = trim((string)Yii::$app->request->post('title'));
        if ($title === '' || mb_strlen($title) > 128) {
            $this->view->error('Enter a title of up to 128 characters.');
        } elseif (!is_numeric($amount) || Money::toCents($amount) < 1) {
            $this->view->error('Enter an amount of at least 0.01.');
        } else {
            $reward->title = $title;
            $reward->default_amount = number_format((float)$amount, 2, '.', '');
            $reward->updated_at = date('Y-m-d H:i:s');
            $reward->save(false, ['title', 'default_amount', 'updated_at']);
            $this->view->success('Reward updated. People already on the list keep their current amounts until you change them.');
        }
        return $this->redirect($this->route('view', ['id' => $reward->id]));
    }

    public function actionRefresh($id)
    {
        $this->guard();
        $reward = $this->findReward($id);
        try {
            $result = (new ListBuilder())->sync($reward);
            $message = $result['added'] . ' added, ' . $result['refreshed'] . ' updated.';
            if ($result['more']) {
                $message .= ' More people are still missing. Refresh again to add the next ' . ListBuilder::BATCH . '.';
            }
            $this->view->success($message);
        } catch (\Throwable $e) {
            $this->view->error($e->getMessage());
        }
        return $this->redirect($this->route('view', ['id' => $reward->id]));
    }

    public function actionSaveList($id)
    {
        $this->guard();
        $reward = $this->findReward($id);
        $ids = (array)Yii::$app->request->post('ids', []);
        $include = array_fill_keys(array_map('strval', (array)Yii::$app->request->post('include', [])), true);
        $amounts = (array)Yii::$app->request->post('amounts', []);
        $actor = Ledger::actor();
        $now = date('Y-m-d H:i:s');
        foreach ($ids as $paymentId) {
            $payment = Payment::findOne(['id' => (int)$paymentId, 'reward_id' => (int)$reward->id]);
            if (!$payment || !$payment->isUnsent()) {
                continue;
            }
            $excluded = isset($include[(string)$payment->id]) ? 0 : 1;
            if ((int)$payment->excluded !== $excluded) {
                $from = (int)$payment->excluded;
                $payment->excluded = $excluded;
                Ledger::add($payment, $excluded ? 'excluded' : 'included', PaymentEvent::SOURCE_MANAGER, $payment->status, $payment->status, $excluded ? 'Left off the payment.' : 'Included in the payment.', null, $actor);
                unset($from);
            }
            if (isset($amounts[$payment->id]) && is_numeric($amounts[$payment->id])) {
                $next = number_format((float)$amounts[$payment->id], 2, '.', '');
                if (Money::toCents($next) !== Money::toCents($payment->amount) && Money::toCents($next) >= 1) {
                    $previous = $payment->amount;
                    $payment->amount = $next;
                    $payment->amount_overridden_by = $actor;
                    $payment->amount_overridden_at = $now;
                    Ledger::add($payment, 'amount_overridden', PaymentEvent::SOURCE_MANAGER, $payment->status, $payment->status, $previous . ' to ' . $next, null, $actor);
                    $payment->last_error = $this->withoutAmountRefusal($payment->last_error);
                }
            }
            Ledger::touch($payment);
            $payment->save(false);
        }
        $this->view->success('List saved.');
        return $this->redirect($this->route('view', ['id' => $reward->id, 'page' => Yii::$app->request->get('page')]));
    }

    public function actionConfirm($id)
    {
        $this->guard();
        $reward = $this->findReward($id);
        try {
            $quote = (new AuthoriseService())->quote($reward);
        } catch (\Throwable $e) {
            $this->view->error($e->getMessage());
            return $this->redirect($this->route('view', ['id' => $reward->id]));
        }
        return $this->render('@thiscovery-incentives/views/manage/confirm', [
            'reward' => $reward,
            'quote' => $quote,
            'space' => $this->scopeSpace(),
        ]);
    }

    public function actionAuthorise($id)
    {
        $this->guard();
        $reward = $this->findReward($id);
        try {
            $result = (new AuthoriseService())->authorise($reward);
            $this->view->success($result['sent'] . ' sent to Ayda. ' . $result['failed'] . ' could not be sent.');
        } catch (\Throwable $e) {
            $this->view->error($e->getMessage());
        }
        return $this->redirect($this->route('view', ['id' => $reward->id]));
    }

    public function actionClose($id)
    {
        $this->guard();
        $reward = $this->findReward($id);
        $reward->status = $reward->isOpen() ? Reward::STATUS_CLOSED : Reward::STATUS_OPEN;
        $reward->updated_at = date('Y-m-d H:i:s');
        $reward->save(false, ['status', 'updated_at']);
        return $this->redirect($this->route('view', ['id' => $reward->id]));
    }

    public function actionPayment($id)
    {
        $this->guard();
        $payment = Payment::findOne((int)$id);
        if (!$payment || !$this->rewardInScope($payment->reward)) {
            throw new NotFoundHttpException();
        }
        return $this->render('@thiscovery-incentives/views/manage/payment', [
            'payment' => $payment,
            'events' => $payment->events,
            'space' => $this->scopeSpace(),
        ]);
    }

    public function actionApprove($id)
    {
        $this->guard();
        $payment = $this->findSentPayment($id);
        $reason = trim((string)Yii::$app->request->post('reason'));
        try {
            (new AydaClient())->approve((string)$payment->ayda_incentive_id, $reason);
            $incentive = (new AydaClient())->incentive((string)$payment->ayda_incentive_id);
            (new AuthoriseService())->applyIncentive($payment, $incentive, Ledger::actor());
            Ledger::add($payment, 'approved', PaymentEvent::SOURCE_MANAGER, Payment::STATUS_REQUIRES_REVIEW, $payment->status, $reason !== '' ? $reason : 'Approved.');
            $this->view->success('Payment approved.');
        } catch (\Throwable $e) {
            $this->view->error($e->getMessage());
        }
        return $this->redirect($this->route('payment', ['id' => $payment->id]));
    }

    public function actionCancel($id)
    {
        $this->guard();
        $payment = $this->findSentPayment($id);
        if ($payment->status === Payment::STATUS_PAID) {
            $this->view->error('A paid incentive cannot be cancelled here.');
            return $this->redirect($this->route('payment', ['id' => $payment->id]));
        }
        $reason = trim((string)Yii::$app->request->post('reason'));
        try {
            (new AydaClient())->cancel((string)$payment->ayda_incentive_id, $reason);
            $from = $payment->status;
            $payment->status = Payment::STATUS_CANCELLED;
            $payment->ayda_status = 'Cancelled';
            Ledger::touch($payment);
            $payment->save(false);
            Ledger::add($payment, 'cancelled', PaymentEvent::SOURCE_MANAGER, $from, Payment::STATUS_CANCELLED, $reason !== '' ? $reason : 'Cancelled.');
            $this->view->success('Payment cancelled.');
        } catch (\Throwable $e) {
            $this->view->error($e->getMessage());
        }
        return $this->redirect($this->route('payment', ['id' => $payment->id]));
    }

    public function actionRegister()
    {
        $this->guard();
        $filter = $this->filterFromRequest();
        return $this->render('@thiscovery-incentives/views/manage/register', [
            'filter' => $filter,
            'provider' => $filter->provider(),
            'totals' => $filter->totals(),
            'space' => $this->scopeSpace(),
            'rewards' => $this->rewardOptions(),
        ]);
    }

    public function actionExport()
    {
        $this->guard();
        $filter = $this->filterFromRequest();
        $csv = (new ExportService())->csv($filter);
        return Yii::$app->response->sendContentAsFile($csv, 'incentives-' . date('Ymd-His') . '.csv', [
            'mimeType' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function guard(): void
    {
        $space = $this->scopeSpace();
        $allowed = $space ? Access::canManageSpace($space) : Access::canManageNetwork();
        if (!$allowed) {
            throw new \yii\web\ForbiddenHttpException();
        }
    }

    protected function findReward($id): Reward
    {
        $reward = Reward::findOne((int)$id);
        if (!$reward || !$this->rewardInScope($reward)) {
            throw new NotFoundHttpException();
        }
        return $reward;
    }

    protected function rewardInScope(?Reward $reward): bool
    {
        if (!$reward) {
            return false;
        }
        $space = $this->scopeSpace();
        if (!$space) {
            return Access::canManageNetwork();
        }
        return (int)$reward->space_id === (int)$space->id && Access::canManageSpace($space);
    }

    protected function findSentPayment($id): Payment
    {
        $payment = Payment::findOne((int)$id);
        if (!$payment || !$payment->ayda_incentive_id || !$this->rewardInScope($payment->reward)) {
            throw new NotFoundHttpException();
        }
        return $payment;
    }

    /**
     * @return array<int, string>
     */
    protected function rewardOptions(): array
    {
        $query = Reward::find()->orderBy(['title' => SORT_ASC]);
        $space = $this->scopeSpace();
        if ($space) {
            $query->andWhere(['space_id' => (int)$space->id]);
        }
        return $query->select('title')->indexBy('id')->column();
    }

    protected function filterFromRequest(): RegisterQuery
    {
        $request = Yii::$app->request;
        $filter = new RegisterQuery();
        $filter->status = trim((string)$request->get('status', $request->post('status', '')));
        $filter->source = trim((string)$request->get('source', $request->post('source', '')));
        $filter->q = trim((string)$request->get('q', $request->post('q', '')));
        $filter->from = trim((string)$request->get('from', $request->post('from', '')));
        $filter->to = trim((string)$request->get('to', $request->post('to', '')));
        $rewardId = (int)$request->get('rewardId', $request->post('rewardId', 0));
        $filter->rewardId = $rewardId > 0 ? $rewardId : null;
        $space = $this->scopeSpace();
        $filter->spaceId = $space ? (int)$space->id : null;
        return $filter;
    }

    protected function route(string $action, array $params = []): array
    {
        $params[0] = ($this->scopeSpace() ? '/thiscovery-incentives/reward/' : '/thiscovery-incentives/admin/') . $action;
        if ($space = $this->scopeSpace()) {
            $params['contentContainer'] = $space;
        }
        return $params;
    }

    private function withoutAmountRefusal(?string $error): ?string
    {
        if ($error === null || trim($error) === '') {
            return null;
        }
        $kept = [];
        foreach (preg_split('/(?<=\.)\s+/', $error) ?: [] as $sentence) {
            if (stripos($sentence, 'requires at least') !== false || stripos($sentence, 'too small') !== false || stripos($sentence, 'minimum incentive') !== false) {
                continue;
            }
            if (trim($sentence) !== '') {
                $kept[] = trim($sentence);
            }
        }
        $text = trim(implode(' ', $kept));
        return $text === '' ? null : $text;
    }
}
