<?php

declare(strict_types=1);

namespace Gupalo\MonologDbalLogger\EasyAdmin\Controller\Helpers;

use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Gupalo\MonologDbalLogger\EasyAdmin\Field\DbalLoggerYamlField;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Yaml\Yaml;

use function Symfony\Component\String\u;

class DbalLoggerCrudField
{
    public static bool $disabled = false;

    public static function panel(string $label, int|string $cols = 12, ?string $icon = null): FormField
    {
        $cols = max(1, min(12, $cols));

        return FormField::addFieldset($label, $icon)->addCssClass(sprintf('field-form_column %s', is_int($cols) ? 'col-md-'.$cols : $cols));
    }

    public static function id(int $cols = 0): IdField
    {
        $result = IdField::new('id')
            ->setLabel('ID')
            ->setColumns($cols)
            ->hideWhenCreating()
            ->setDisabled()
            ->setRequired(false);

        if (!$cols) {
            $result->hideOnForm();
        }

        return $result;
    }

    public static function createdAt(int $cols = 12): DateTimeField
    {
        return DateTimeField::new('createdAt')
            ->setFormat('YYYY-MM-dd HH:mm:ss')
            ->setLabel('Created')
            ->setColumns($cols)
            ->setDisabled()
            ->setRequired(false)
            ->hideWhenCreating();
    }

    public static function virtual(string $label, callable $callable): TextField
    {
        $field = TextField::new('virtual')
            ->setColumns(12)
            ->setRequired(false)
            ->setDisabled()
            ->setVirtual(true)
            ->setSortable(false)
            ->hideOnForm()
            ->formatValue($callable);

        if ('' !== $label) {
            $field->setLabel($label);
        }

        return $field;
    }

    public static function yaml(string $property, int $cols = 12, int $rows = 10, bool $isIndex = false, int $inline = 2, string|int $maxWidth = 500): DbalLoggerYamlField|TextField
    {
        if ($isIndex) {
            return self::virtual(
                $property,
                static fn ($d, $v) => self::yamlDumpHtml(PropertyAccess::createPropertyAccessor()->getValue($v, $property), inline: $inline, maxWidth: $maxWidth)
            )->renderAsHtml()->setLabel(self::humanizeString($property));
        }

        return DbalLoggerYamlField::new($property)
            ->setColumns($cols)
            ->setNumOfRows($rows)
            ->setRequired(false);
    }

    private static function yamlDumpHtml(mixed $a, int $inline = 2, string|int $maxWidth = 500): string
    {
        if (is_int($maxWidth) || preg_match('#^\d+$#', $maxWidth)) {
            $maxWidth = sprintf('%spx', $maxWidth);
        }

        return sprintf(
            '<span style="white-space: pre; display: block; max-width: %s; overflow: hidden">%s</span>',
            $maxWidth,
            htmlspecialchars(Yaml::dump($a, inline: $inline))
        );
    }

    private static function humanizeString(string $string): string
    {
        $uString = u($string);
        $upperString = $uString->upper()->toString();

        // this prevents humanizing all-uppercase labels (e.g. 'UUID' -> 'U u i d')
        // and other special labels which look better in uppercase
        if ($uString->toString() === $upperString) {
            return $upperString;
        }

        return $uString
            ->replaceMatches('/([A-Z])/', '_$1')
            ->replaceMatches('/[_\s]+/', ' ')
            ->trim()
            ->lower()
            ->title(true)
            ->toString();
    }
}
