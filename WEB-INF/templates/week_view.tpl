{* Copyright (c) Anuko International Ltd. https://www.anuko.com
License: See license.txt *}

{$forms.weekViewForm.open}
<table class="centered-table">
  <tr class = "small-screen-label"><td><label for="week_menu">{$i18n.label.menu}:</label></td></tr>
  <tr>
    <td class="large-screen-label"><label for="week_menu">{$i18n.label.menu}:</label></td>
    <td class="td-with-input">{$forms.weekViewForm.week_menu.control}
    </td>
  </tr>
  <tr><td><div class="small-screen-form-control-separator"></div></td></tr>
  <tr class = "small-screen-label"><td><label for="week_note">{$i18n.label.week_note}:</label></td></tr>
  <tr>
    <td class="large-screen-label"><label for="week_note">{$i18n.label.week_note}:</label></td>
    <td class="td-with-input">{$forms.weekViewForm.week_note.control}
    </td>
  </tr>
  <tr><td><div class="small-screen-form-control-separator"></div></td></tr>
  <tr class = "small-screen-label"><td><label for="week_list">{$i18n.label.week_list}:</label></td></tr>
  <tr>
    <td class="large-screen-label"><label for="week_list">{$i18n.label.week_list}:</label></td>
    <td class="td-with-input">{$forms.weekViewForm.week_list.control}
    </td>
  </tr>
  <tr><td><div class="small-screen-form-control-separator"></div></td></tr>
  <tr class = "small-screen-label"><td><label for="notes">{$i18n.label.notes}:</label></td></tr>
  <tr>
    <td class="large-screen-label"><label for="notes">{$i18n.label.notes}:</label></td>
    <td class="td-with-input">{$forms.weekViewForm.notes.control}
    </td>
  </tr>
  <tr><td><div class="small-screen-form-control-separator"></div></td></tr>
  <tr class = "small-screen-label"><td><label for="weekends">{$i18n.label.weekends}:</label></td></tr>
  <tr>
    <td class="large-screen-label"><label for="weekends">{$i18n.label.weekends}:</label></td>
    <td class="td-with-input">{$forms.weekViewForm.weekends.control}
    </td>
  </tr>
  <tr><td><div class="small-screen-form-control-separator"></div></td></tr>
</table>
<div class="button-set">{$forms.weekViewForm.btn_save.control}</div>
{$forms.weekViewForm.close}
