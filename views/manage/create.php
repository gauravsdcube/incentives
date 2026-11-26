<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */


use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryIncentives\helpers\Links;
use humhub\modules\thiscoveryIncentives\models\Reward;
use humhub\modules\thiscoveryIncentives\models\RewardForm;
use humhub\modules\thiscoveryIncentives\services\Currencies;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
use yii\web\View;

/** @var RewardForm $model */
/** @var humhub\modules\space\models\Space|null $space */
/** @var array $forms */
/** @var array $groups */
/** @var array $spaces */
/** @var Reward|null $reward */

$editing = (bool)$model->rewardId;
$this->title = $editing ? 'Edit reward' : 'New reward';
$fieldTypes = class_exists(FormField::class);
$cancel = $editing && isset($reward) ? Links::to($space, 'view', ['id' => $reward->id]) : Links::to($space, 'index');
$survey = $model->source === Reward::SOURCE_FORM;
?>
<div class="panel panel-default">
    <div class="panel-heading"><?= Html::encode($this->title) ?></div>
    <div class="panel-body">
        <?= $this->render('_nav', ['space' => $space, 'active' => 'index']) ?>
        <?php if ($model->hasErrors()): ?>
            <div class="alert alert-danger"><?= Html::encode(implode(' ', $model->getErrorSummary(true))) ?></div>
        <?php endif; ?>
        <?php $form = ActiveForm::begin(); ?>
            <?= $form->field($model, 'title')->textInput(['maxlength' => 128])->hint($model->currencyLocked ? 'Ayda already has this project. People still see the original name when they claim.' : '') ?>
            <?php if ($editing): ?>
                <?= Html::activeHiddenInput($model, 'source') ?>
                <p><?= $model->source === Reward::SOURCE_FORM ? 'Survey participants.' : 'Community members.' ?></p>
            <?php elseif (!$space): ?>
                <?= $form->field($model, 'source')->dropDownList([
                    Reward::SOURCE_COMMUNITY => 'Community members',
                    Reward::SOURCE_FORM => 'Survey participants',
                ]) ?>
            <?php else: ?>
                <?= Html::activeHiddenInput($model, 'source') ?>
                <p><?= $model->source === Reward::SOURCE_FORM ? 'Survey participants in this space.' : 'Members of this space.' ?></p>
            <?php endif; ?>

            <fieldset id="incentive-survey" class="border-0 p-0 m-0" <?= $survey ? '' : 'disabled style="display:none"' ?>>
                <?php if ($model->formLocked): ?>
                    <?= Html::activeHiddenInput($model, 'form_id') ?>
                    <p>Form: <?= Html::encode($forms[(int)$model->form_id] ?? 'This form') ?>. People are already on the list, so the form stays the same.</p>
                <?php else: ?>
                    <?= $form->field($model, 'form_id')->dropDownList($forms, ['prompt' => 'Choose a form']) ?>
                    <?php if ($forms === []): ?>
                        <p class="text-muted">No forms are available. Turn on Enable incentives under a form’s Basic settings. A form that already has a reward is not listed.</p>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($model->form_id): ?>
                    <p class="text-muted">Map the email, first name, and last name questions. A response that does not contain all three cannot be paid.</p>
                    <?= $form->field($model, 'mode')->dropDownList([
                        Reward::MODE_ALL => 'Pay everyone who completes',
                        Reward::MODE_OPT_IN => 'Pay only people who opt in',
                    ]) ?>
                    <?= $form->field($model, 'email_field_id')->dropDownList($model->fieldOptions((int)$model->form_id, $fieldTypes ? [FormField::TYPE_EMAIL, FormField::TYPE_TEXT] : null), ['prompt' => 'Email question']) ?>
                    <?= $form->field($model, 'first_name_field_id')->dropDownList($model->fieldOptions((int)$model->form_id, $fieldTypes ? [FormField::TYPE_TEXT] : null), ['prompt' => 'First name question']) ?>
                    <?= $form->field($model, 'last_name_field_id')->dropDownList($model->fieldOptions((int)$model->form_id, $fieldTypes ? [FormField::TYPE_TEXT] : null), ['prompt' => 'Last name question']) ?>
                    <?= $form->field($model, 'opt_in_field_id')->dropDownList($model->fieldOptions((int)$model->form_id, $fieldTypes ? [FormField::TYPE_RADIO, FormField::TYPE_DROPDOWN, FormField::TYPE_CHECKBOX] : null), ['prompt' => 'Opt-in question']) ?>
                    <?= $form->field($model, 'opt_in_value')->textarea(['rows' => 3, 'maxlength' => 2000])->hint('The choice code or the choice text. A response counts when the stored answer matches either.') ?>
                <?php else: ?>
                    <p class="text-muted">Choose a form and continue to map its questions.</p>
                <?php endif; ?>
            </fieldset>

            <fieldset id="incentive-community" class="border-0 p-0 m-0" <?= $survey ? 'disabled style="display:none"' : '' ?>>
                <?php if (!$space): ?>
                    <?= $form->field($model, 'audience')->dropDownList([
                        Reward::AUDIENCE_NETWORK => 'Whole network',
                        Reward::AUDIENCE_SPACE => 'One space',
                        Reward::AUDIENCE_GROUP => 'One user group',
                    ]) ?>
                    <?= $form->field($model, 'space_id', ['options' => ['style' => $model->audience === Reward::AUDIENCE_SPACE ? null : 'display:none']])->dropDownList($spaces, ['prompt' => 'Choose a space', 'disabled' => $model->audience !== Reward::AUDIENCE_SPACE]) ?>
                    <?= $form->field($model, 'group_id', ['options' => ['style' => $model->audience === Reward::AUDIENCE_GROUP ? null : 'display:none']])->dropDownList($groups, ['prompt' => 'Choose a group', 'disabled' => $model->audience !== Reward::AUDIENCE_GROUP]) ?>
                <?php else: ?>
                    <?= Html::activeHiddenInput($model, 'audience') ?>
                    <?= Html::activeHiddenInput($model, 'space_id') ?>
                <?php endif; ?>
            </fieldset>

            <?php if ($model->currencyLocked): ?>
                <?= Html::activeHiddenInput($model, 'currency') ?>
                <p>Currency: <?= Html::encode($model->currency) ?>. An Ayda project is already open in this currency.</p>
            <?php else: ?>
                <?= $form->field($model, 'currency')->dropDownList(Currencies::options()) ?>
            <?php endif; ?>
            <?= $form->field($model, 'default_amount')->textInput()->hint('People already on the list keep their own amount. Change a person in the list before authorising.') ?>
            <?php if (!$editing && $survey && !$model->form_id): ?>
                <?= Html::submitButton('Continue', ['class' => 'btn btn-default', 'name' => 'continue', 'value' => '1', 'id' => 'incentive-save', 'data-editing' => $editing ? '1' : '0']) ?>
            <?php else: ?>
                <?= Html::submitButton($editing ? 'Save changes' : 'Save reward', ['class' => 'btn btn-primary', 'name' => 'save', 'value' => '1', 'id' => 'incentive-save', 'data-editing' => $editing ? '1' : '0']) ?>
            <?php endif; ?>
            <?= Html::a('Cancel', $cancel, ['class' => 'btn btn-link']) ?>
        <?php ActiveForm::end(); ?>
    </div>
</div>
<?php
$this->registerJs(<<<'JS'
function incentiveApplyAudience() {
    var audience = $('#rewardform-audience').val() || 'network';
    $('.field-rewardform-space_id').toggle(audience === 'space');
    $('.field-rewardform-group_id').toggle(audience === 'group');
    $('#rewardform-space_id').prop('disabled', audience !== 'space');
    $('#rewardform-group_id').prop('disabled', audience !== 'group');
}
function incentiveApplySource() {
    var source = $('#rewardform-source').val() || 'community';
    var survey = source === 'form';
    $('#incentive-survey').prop('disabled', !survey).toggle(survey);
    $('#incentive-community').prop('disabled', survey).toggle(!survey);
    if (!survey) {
        $('#incentive-save').attr('name', 'save').attr('value', '1').text($('#incentive-save').data('editing') ? 'Save changes' : 'Save reward').addClass('btn-primary');
        incentiveApplyAudience();
    }
}
$(document).off('change.incentiveSource').on('change.incentiveSource', '#rewardform-source', incentiveApplySource);
$(document).off('change.incentiveAudience').on('change.incentiveAudience', '#rewardform-audience', incentiveApplyAudience);
$(document).off('change.incentiveForm').on('change.incentiveForm', '#rewardform-form_id', function () {
    var url = new URL(window.location.href);
    url.searchParams.set('source', 'form');
    url.searchParams.set('formId', this.value || '');
    url.searchParams.delete('_pjax');
    window.location = url.toString();
});
incentiveApplySource();
JS, View::POS_READY);
?>
