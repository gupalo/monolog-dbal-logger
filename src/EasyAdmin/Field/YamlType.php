<?php

namespace Gupalo\MonologDbalLogger\EasyAdmin\Field;

use EasyCorp\Bundle\EasyAdminBundle\Form\Type\CodeEditorType;
use Gupalo\SymfonyFormTransformers\Transformer\JsonYamlTransformer;
use Symfony\Component\Form\FormBuilderInterface;

class YamlType extends CodeEditorType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);
        $builder->addViewTransformer(new JsonYamlTransformer());
    }
}
