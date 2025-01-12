<?php

namespace Gupalo\MonologDbalLogger\EasyAdmin\Field;

use EasyCorp\Bundle\EasyAdminBundle\Config\Asset;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use InvalidArgumentException;
use Symfony\Contracts\Translation\TranslatableInterface;

class YamlField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_INDENT_WITH_TABS = 'indentWithTabs';
    public const OPTION_LANGUAGE = 'language';
    public const OPTION_NUM_OF_ROWS = 'numOfRows';
    public const OPTION_TAB_SIZE = 'tabSize';
    public const OPTION_SHOW_LINE_NUMBERS = 'showLineNumbers';

    /**
     * @param TranslatableInterface|string|false|null $label
     * @noinspection PhpInternalEntityUsedInspection
     */
    public static function new(string $propertyName, $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/code_editor')
            ->setFormType(YamlType::class)
            ->addCssClass('field-code_editor')
            ->addCssFiles(Asset::fromEasyAdminAssetPackage('field-code-editor.css')->onlyOnForms())
            ->addJsFiles(Asset::fromEasyAdminAssetPackage('field-code-editor.js')->onlyOnForms())
            ->setDefaultColumns('col-md-12 col-xxl-10')
            ->setCustomOption(self::OPTION_INDENT_WITH_TABS, false)
            ->setCustomOption(self::OPTION_LANGUAGE, 'yaml')
            ->setCustomOption(self::OPTION_NUM_OF_ROWS, null)
            ->setCustomOption(self::OPTION_TAB_SIZE, 4)
            ->setCustomOption(self::OPTION_SHOW_LINE_NUMBERS, true);
    }

    public function setNumOfRows(int $rows): self
    {
        if ($rows < 1) {
            throw new InvalidArgumentException(sprintf('The argument of the "%s()" method must be 1 or higher (%d given).', __METHOD__, $rows));
        }

        $this->setCustomOption(self::OPTION_NUM_OF_ROWS, $rows);

        return $this;
    }

    public function hideLineNumbers(bool $hideNumbers = true): self
    {
        $this->setCustomOption(self::OPTION_SHOW_LINE_NUMBERS, !$hideNumbers);

        return $this;
    }
}
