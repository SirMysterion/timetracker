{* Copyright (c) Anuko International Ltd. https://www.anuko.com
License: See license.txt *}

{$forms.workUnitsForm.open}
<table class="centered-table">
  <tr class = "small-screen-label"><td><label for="minutes_in_unit">{$i18n.form.work_units.minutes_in_unit}:</label></td></tr>
  <tr>
    <td class="large-screen-label"><label for="minutes_in_unit">{$i18n.form.work_units.minutes_in_unit}:</label></td>
    <td class="td-with-input">{$forms.workUnitsForm.minutes_in_unit.control}
    </td>
  </tr>
  <tr><td><div class="small-screen-form-control-separator"></div></td></tr>
  <tr class = "small-screen-label"><td><label for="1st_unit_threshold">{$i18n.form.work_units.1st_unit_threshold}:</label></td></tr>
  <tr>
    <td class="large-screen-label"><label for="1st_unit_threshold">{$i18n.form.work_units.1st_unit_threshold}:</label></td>
    <td class="td-with-input">{$forms.workUnitsForm.1st_unit_threshold.control}
    </td>
  </tr>
  <tr><td><div class="small-screen-form-control-separator"></div></td></tr>
  <tr class = "small-screen-label"><td><label for="totals_only">{$i18n.label.totals_only}:</label></td></tr>
  <tr>
    <td class="large-screen-label"><label for="totals_only">{$i18n.label.totals_only}:</label></td>
    <td class="td-with-input">{$forms.workUnitsForm.totals_only.control}
    </td>
  </tr>
  <tr><td><div class="small-screen-form-control-separator"></div></td></tr>
</table>
<div class="button-set">{$forms.workUnitsForm.btn_save.control}</div>
{$forms.workUnitsForm.close}
