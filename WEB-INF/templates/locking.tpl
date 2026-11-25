{* Copyright (c) Anuko International Ltd. https://www.anuko.com
License: See license.txt *}

{$forms.lockingForm.open}
<table class="centered-table">
  <tr class = "small-screen-label"><td><label for="lock_spec">{$i18n.label.schedule}:</label></td></tr>
  <tr>
    <td class="large-screen-label"><label for="lock_spec">{$i18n.label.schedule}:</label></td>
    <td class="td-with-input">{$forms.lockingForm.lock_spec.control}
    </td>
  </tr>
  <tr><td><div class="small-screen-form-control-separator"></div></td></tr>
</table>
<div class="button-set">{$forms.lockingForm.btn_save.control}</div>
{$forms.lockingForm.close}
