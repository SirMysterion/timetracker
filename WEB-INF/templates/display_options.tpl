{* Copyright (c) Anuko International Ltd. https://www.anuko.com
License: See license.txt *}

{$forms.displayOptionsForm.open}
<table class="centered-table">
  <tr><td class="sectionHeaderNoBorder" colspan="2">{$i18n.title.time}</td></tr>
  <tr class = "small-screen-label"><td><label for="time_note_on_separate_row">{$i18n.form.display_options.note_on_separate_row}:</label></td></tr>
  <tr>
    <td class="large-screen-label"><label for="time_note_on_separate_row">{$i18n.form.display_options.note_on_separate_row}:</label></td>
    <td class="td-with-input">{$forms.displayOptionsForm.time_note_on_separate_row.control}
    </td>
  </tr>
  <tr><td><div class="small-screen-form-control-separator"></div></td></tr>
  <tr class = "small-screen-label"><td><label for="time_not_complete_days">{$i18n.form.display_options.not_complete_days}:</label></td></tr>
  <tr>
    <td class="large-screen-label"><label for="time_not_complete_days">{$i18n.form.display_options.not_complete_days}:</label></td>
    <td class="td-with-input">{$forms.displayOptionsForm.time_not_complete_days.control}
    </td>
  </tr>
  <tr><td><div class="small-screen-form-control-separator"></div></td></tr>
  <tr class = "small-screen-label"><td><label for="record_custom_fields">{$i18n.label.custom_fields}:</label></td></tr>
  <tr>
    <td class="large-screen-label"><label for="record_custom_fields">{$i18n.label.custom_fields}:</label></td>
    <td class="td-with-input">{$forms.displayOptionsForm.record_custom_fields.control}
    </td>
  </tr>
  <tr><td><div class="small-screen-form-control-separator"></div></td></tr>
  <tr><td><div class="form-control-separator"></div></td></tr>
  <tr><td class="sectionHeaderNoBorder" colspan="2">{$i18n.title.reports}</td></tr>
  <tr class = "small-screen-label"><td><label for="report_note_on_separate_row">{$i18n.form.display_options.note_on_separate_row}:</label></td></tr>
  <tr>
    <td class="large-screen-label"><label for="report_note_on_separate_row">{$i18n.form.display_options.note_on_separate_row}:</label></td>
    <td class="td-with-input">{$forms.displayOptionsForm.report_note_on_separate_row.control}
    </td>
  </tr>
  <tr class = "small-screen-label"><td><label for="report_inactive_projects">{$i18n.form.display_options.inactive_projects}:</label></td></tr>
  <tr>
    <td class="large-screen-label"><label for="report_inactive_projects">{$i18n.form.display_options.inactive_projects}:</label></td>
    <td class="td-with-input">{$forms.displayOptionsForm.report_inactive_projects.control}
    </td>
  </tr>
  <tr class = "small-screen-label"><td><label for="report_cost_per_hour">{$i18n.form.display_options.cost_per_hour}:</label></td></tr>
  <tr>
    <td class="large-screen-label"><label for="report_cost_per_hour">{$i18n.form.display_options.cost_per_hour}:</label></td>
    <td class="td-with-input">{$forms.displayOptionsForm.report_cost_per_hour.control}
    </td>
  </tr>
  <tr><td><div class="small-screen-form-control-separator"></div></td></tr>
  <tr><td><div class="form-control-separator"></div></td></tr>
  <tr><td class="sectionHeaderNoBorder" colspan="2">{$i18n.form.display_options.custom_css}</td></tr>
  <tr><td colspan="2">{$forms.displayOptionsForm.custom_css.control}</td></tr>
  <tr><td colspan="2"></td></tr>
  <tr><td><div class="small-screen-form-control-separator"></div></td></tr>
  <tr><td><div class="form-control-separator"></div></td></tr>
  <tr><td class="sectionHeaderNoBorder" colspan="2">{$i18n.form.display_options.custom_translation}</td></tr>
  <tr><td colspan="2">{$forms.displayOptionsForm.custom_translation.control}</td></tr>
  <tr><td colspan="2"></td></tr>
  <tr><td><div class="small-screen-form-control-separator"></div></td></tr>
</table>
<div class="button-set">{$forms.displayOptionsForm.btn_save.control}</div>
{$forms.displayOptionsForm.close}
