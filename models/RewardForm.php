<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\models;

use humhub\modules\space\models\Space;
use humhub\modules\thiscoveryIncentives\services\Currencies;
use humhub\modules\user\models\Group;
use Yii;
use yii\base\Model;

class RewardForm extends Model
{
    public $title = '';
    public $source = Reward::SOURCE_COMMUNITY;
    public $form_id;
    public $audience = Reward::AUDIENCE_NETWORK;
    public $space_id;
    public $group_id;
    public $currency = 'GBP';
    public $default_amount = '10.00';
    public $mode = Reward::MODE_ALL;
    public $email_field_id;
    public $first_name_field_id;
    public $last_name_field_id;
    public $opt_in_field_id;
    public $opt_in_value = 'Yes';

    /** @var Space|null When set, the reward belongs to this space. */
    public $fixedSpace;

    /** @var int|null Set while an existing reward is being edited. */
    public $rewardId;

    /** @var bool The Ayda project already exists, so its currency cannot change. */
    public $currencyLocked = false;

    /** @var bool People are already on the list, so the form cannot be swapped. */
    public $formLocked = false;

    public function rules()
    {
        return [
            [['title', 'source', 'currency', 'default_amount'], 'required'],
            [['title'], 'string', 'max' => 128],
            [['source'], 'in', 'range' => [Reward::SOURCE_FORM, Reward::SOURCE_COMMUNITY]],
            [['audience'], 'in', 'range' => [Reward::AUDIENCE_NETWORK, Reward::AUDIENCE_SPACE, Reward::AUDIENCE_GROUP, Reward::AUDIENCE_FORM]],
            [['mode'], 'in', 'range' => [Reward::MODE_OPT_IN, Reward::MODE_ALL, Reward::MODE_MANUAL]],
            [['currency'], 'in', 'range' => Currencies::CODES],
            [['default_amount'], 'number', 'min' => 0.01],
            [['form_id', 'space_id', 'group_id', 'email_field_id', 'first_name_field_id', 'last_name_field_id', 'opt_in_field_id'], 'integer'],
            [['opt_in_value'], 'string', 'max' => 64],
            [['form_id'], 'required', 'when' => function (self $model) {
                return $model->source === Reward::SOURCE_FORM;
            }, 'whenClient' => "function (attribute, value) {
                return $('#rewardform-source').val() === 'form';
            }"],
            [['email_field_id', 'first_name_field_id', 'last_name_field_id'], 'required', 'when' => function (self $model) {
                return $model->source === Reward::SOURCE_FORM;
            }, 'whenClient' => "function (attribute, value) {
                return $('#rewardform-source').val() === 'form';
            }"],
            [['opt_in_field_id', 'opt_in_value'], 'required', 'when' => function (self $model) {
                return $model->source === Reward::SOURCE_FORM && $model->mode === Reward::MODE_OPT_IN;
            }, 'whenClient' => "function (attribute, value) {
                return $('#rewardform-source').val() === 'form' && $('#rewardform-mode').val() === 'opt_in';
            }"],
            [['group_id'], 'required', 'when' => function (self $model) {
                return $model->source === Reward::SOURCE_COMMUNITY && $model->audience === Reward::AUDIENCE_GROUP;
            }, 'whenClient' => "function (attribute, value) {
                return $('#rewardform-source').val() !== 'form' && $('#rewardform-audience').val() === 'group';
            }"],
            [['space_id'], 'required', 'when' => function (self $model) {
                return $model->source === Reward::SOURCE_COMMUNITY && $model->audience === Reward::AUDIENCE_SPACE && !$model->fixedSpace;
            }, 'whenClient' => "function (attribute, value) {
                return $('#rewardform-source').val() !== 'form' && $('#rewardform-audience').val() === 'space';
            }"],
            [['source'], 'validateShape'],
        ];
    }

    public function validateShape(): void
    {
        if ($this->fixedSpace instanceof Space) {
            $this->space_id = (int)$this->fixedSpace->id;
            if ($this->source === Reward::SOURCE_COMMUNITY) {
                $this->audience = Reward::AUDIENCE_SPACE;
            }
        }
        if ($this->source === Reward::SOURCE_FORM) {
            $this->validateForm();
            return;
        }
        $this->form_id = null;
        $this->email_field_id = null;
        $this->first_name_field_id = null;
        $this->last_name_field_id = null;
        $this->opt_in_field_id = null;
        $this->mode = Reward::MODE_MANUAL;
        if ($this->audience === Reward::AUDIENCE_GROUP && !Group::findOne((int)$this->group_id)) {
            $this->addError('group_id', 'Choose a user group.');
        }
        if ($this->audience === Reward::AUDIENCE_SPACE && !Space::findOne((int)$this->space_id)) {
            $this->addError('space_id', 'Choose a space.');
        }
        if ($this->audience === Reward::AUDIENCE_NETWORK) {
            $this->space_id = null;
            $this->group_id = null;
        }
    }

    private function validateForm(): void
    {
        if (!class_exists(\humhub\modules\thiscoveryForms\models\CustomForm::class)) {
            $this->addError('form_id', 'Thiscovery Forms is not available.');
            return;
        }
        $form = \humhub\modules\thiscoveryForms\models\CustomForm::findOne((int)$this->form_id);
        if (!$form) {
            $this->addError('form_id', 'Choose a form.');
            return;
        }
        if (!$form->incentivesEnabled() && !$this->rewardId) {
            $this->addError('form_id', 'Turn on Enable incentives in the form’s Basic settings first.');
            return;
        }
        if ($this->fixedSpace instanceof Space) {
            $container = $form->content->container ?? null;
            if (!$container instanceof Space || (int)$container->id !== (int)$this->fixedSpace->id) {
                $this->addError('form_id', 'Choose a form from this space.');
                return;
            }
        }
        $fields = $this->fieldMap((int)$form->id);
        foreach (['email_field_id' => 'email', 'first_name_field_id' => 'first name', 'last_name_field_id' => 'last name'] as $attr => $label) {
            $id = (int)$this->$attr;
            if (!isset($fields[$id])) {
                $this->addError($attr, 'Choose the ' . $label . ' question.');
            }
        }
        if ($this->mode === Reward::MODE_OPT_IN && !isset($fields[(int)$this->opt_in_field_id])) {
            $this->addError('opt_in_field_id', 'Choose the opt-in question.');
        }
        $existing = Reward::find()->where(['form_id' => (int)$form->id])->one();
        if ($existing && (int)$existing->id !== (int)$this->rewardId) {
            $this->addError('form_id', 'This form already has the reward "' . $existing->title . '". Open that reward, or choose a form that does not have one yet.');
        }
        $container = $form->content->container ?? null;
        $this->audience = Reward::AUDIENCE_FORM;
        $this->space_id = $container instanceof Space ? (int)$container->id : null;
        $this->group_id = null;
    }

    /**
     * @return array<int, string>
     */
    public function fieldOptions(?int $formId, ?array $types = null): array
    {
        if (!$formId || !class_exists(\humhub\modules\thiscoveryForms\models\FormField::class)) {
            return [];
        }
        $query = \humhub\modules\thiscoveryForms\models\FormField::find()
            ->where(['form_id' => $formId, 'deleted_at' => null])
            ->orderBy(['sort_order' => SORT_ASC]);
        if ($types) {
            $query->andWhere(['type' => $types]);
        }
        $options = [];
        foreach ($query->all() as $field) {
            $label = trim(html_entity_decode(strip_tags((string)$field->label), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $options[(int)$field->id] = ($label !== '' ? $label : 'Question ' . $field->id) . ' (' . $field->type . ')';
        }
        return $options;
    }

    /**
     * @return array<int, true>
     */
    private function fieldMap(int $formId): array
    {
        return array_fill_keys(array_keys($this->fieldOptions($formId)), true);
    }

    public function fromReward(Reward $reward): void
    {
        $this->rewardId = (int)$reward->id;
        $this->title = (string)$reward->title;
        $this->source = (string)$reward->source;
        $this->form_id = $reward->form_id ? (int)$reward->form_id : null;
        $this->audience = (string)$reward->audience;
        $this->space_id = $reward->space_id ? (int)$reward->space_id : null;
        $this->group_id = $reward->group_id ? (int)$reward->group_id : null;
        $this->currency = (string)$reward->currency;
        $this->default_amount = (string)$reward->default_amount;
        $this->mode = (string)$reward->mode;
        $this->email_field_id = $reward->email_field_id ? (int)$reward->email_field_id : null;
        $this->first_name_field_id = $reward->first_name_field_id ? (int)$reward->first_name_field_id : null;
        $this->last_name_field_id = $reward->last_name_field_id ? (int)$reward->last_name_field_id : null;
        $this->opt_in_field_id = $reward->opt_in_field_id ? (int)$reward->opt_in_field_id : null;
        $this->opt_in_value = (string)($reward->opt_in_value ?: 'Yes');
        $this->currencyLocked = trim((string)$reward->ayda_project_id) !== '';
        $this->formLocked = Payment::find()->where(['reward_id' => (int)$reward->id])->exists();
    }

    public function attributeLabels()
    {
        return [
            'title' => 'Title',
            'source' => 'Who is paid',
            'form_id' => 'Form',
            'audience' => 'Audience',
            'space_id' => 'Space',
            'group_id' => 'User group',
            'currency' => 'Currency',
            'default_amount' => 'Default amount',
            'mode' => 'Who qualifies',
            'email_field_id' => 'Email question',
            'first_name_field_id' => 'First name question',
            'last_name_field_id' => 'Last name question',
            'opt_in_field_id' => 'Opt-in question',
            'opt_in_value' => 'Answer that means yes',
        ];
    }

    public function toReward(Reward $reward): void
    {
        $reward->source = $this->source;
        $reward->title = trim((string)$this->title);
        $reward->form_id = $this->source === Reward::SOURCE_FORM ? (int)$this->form_id : null;
        $reward->space_id = $this->space_id ? (int)$this->space_id : null;
        $reward->audience = (string)$this->audience;
        $reward->group_id = $this->audience === Reward::AUDIENCE_GROUP ? (int)$this->group_id : null;
        $reward->currency = strtoupper((string)$this->currency);
        $reward->default_amount = number_format((float)$this->default_amount, 2, '.', '');
        $reward->mode = (string)$this->mode;
        $reward->email_field_id = $this->email_field_id ? (int)$this->email_field_id : null;
        $reward->first_name_field_id = $this->first_name_field_id ? (int)$this->first_name_field_id : null;
        $reward->last_name_field_id = $this->last_name_field_id ? (int)$this->last_name_field_id : null;
        $reward->opt_in_field_id = $this->mode === Reward::MODE_OPT_IN ? (int)$this->opt_in_field_id : null;
        $reward->opt_in_value = $this->mode === Reward::MODE_OPT_IN ? trim((string)$this->opt_in_value) : null;
    }

    /**
     * @return array<int, string>
     */
    public static function formOptions(?Space $space, ?int $includeFormId = null): array
    {
        if (!class_exists(\humhub\modules\thiscoveryForms\models\CustomForm::class)) {
            return [];
        }
        $class = \humhub\modules\thiscoveryForms\models\CustomForm::class;
        $query = $class::find()->andWhere(['is_template' => 0])->orderBy(['title' => SORT_ASC]);
        if ($space) {
            $query->contentContainer($space);
        }
        $used = array_fill_keys(array_map('intval', Reward::find()->select('form_id')->andWhere(['not', ['form_id' => null]])->column()), true);
        $options = [];
        foreach ($query->all() as $form) {
            $formId = (int)$form->id;
            if (isset($used[$formId]) && $formId !== (int)$includeFormId) {
                continue;
            }
            if (!$form->incentivesEnabled() && $formId !== (int)$includeFormId) {
                continue;
            }
            $options[$formId] = $form->title;
        }
        if ($includeFormId && !isset($options[$includeFormId])) {
            $current = $class::findOne($includeFormId);
            if ($current) {
                $options[(int)$current->id] = $current->title;
            }
        }
        return $options;
    }
}
