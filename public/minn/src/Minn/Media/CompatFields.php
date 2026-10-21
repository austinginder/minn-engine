<?php

declare(strict_types=1);

namespace Minn\Media;

use Minn\Support\Escape;

/**
 * The attachment's extra fields as the media modal's compat form prints
 * them (get_compat_media_markup), measured on the reference (probe
 * media-template-doubts): a hidden menu order, the required-fields note and
 * a table of rows, a field's own "tr" as given, then its hidden fields. A
 * text input's value is escaped; a textarea's value, a label and an html
 * field print as given; any other input prints the field's entry of that
 * name. Nothing at all when there are no fields.
 */
final class CompatFields
{
    /**
     * The form's markup.
     *
     * @param array<string, mixed> $fields what attachment_fields_to_edit answered
     */
    public static function markup(int $id, int $menuOrder, array $fields, string $requiredNote): string
    {
        if ($fields === []) {
            return '';
        }
        $rows = '';
        $hidden = '';
        foreach ($fields as $key => $field) {
            $field = (array) $field;
            if (!empty($field['tr'])) {
                $rows .= (string) $field['tr'];
                continue;
            }
            $input = (string) ($field['input'] ?? 'text');
            if ($input === 'hidden') {
                $hidden .= '<input type="hidden" name="attachments[' . $id . '][' . $key . ']" value="' . Escape::attr($field['value'] ?? '') . '" />' . "\n";
                continue;
            }
            $rows .= self::row($id, (string) $key, $field, $input);
        }
        $table = $rows === '' ? '' : '<p class="media-types media-types-required-info"><span class="required-field-message">' . $requiredNote . '</span></p><table class="compat-attachment-fields">' . $rows . '</table>';
        return '<input type="hidden" name="attachments[' . $id . '][menu_order]" value="' . $menuOrder . '" />' . $table . $hidden;
    }

    /** @param array<string, mixed> $field */
    private static function row(int $id, string $key, array $field, string $input): string
    {
        $required = !empty($field['required']);
        $name = "attachments-{$id}-{$key}";
        $control = match ($input) {
            'text' => "<input type='text' class='text' id='{$name}' name='attachments[{$id}][{$key}]' value='" . Escape::attr($field['value'] ?? '') . "' " . ($required ? ' required' : '') . ' />',
            'textarea' => "<textarea id='{$name}' name='attachments[{$id}][{$key}]'" . ($required ? ' required' : '') . '>' . (string) ($field['value'] ?? '') . '</textarea>',
            default => (string) ($field[$input] ?? ''),
        };
        $helps = implode("\n", array_map(static fn ($help): string => "<p class='help'>" . (string) $help . '</p>', (array) ($field['helps'] ?? [])));
        $row = "\t\t<tr class='compat-field-{$key}" . ($required ? ' form-required' : '') . "'>\t\t\t<th scope='row' class='label'><label for='{$name}'><span class='alignleft'>" . (string) ($field['label'] ?? '') . '</span>'
            . ($required ? ' <span class="required">*</span>' : '') . "<br class='clear' /></label></th>\n\t\t\t<td class='field'>" . $control . $helps . "</td>\n\t\t</tr>\n";
        foreach ((array) ($field['extra_rows'] ?? []) as $class => $html) {
            $row .= "\t\t<tr><td></td><td class='{$class}'>" . (string) $html . "</td></tr>\n";
        }
        return $row;
    }
}
