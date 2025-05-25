<?php

declare(strict_types=1);

namespace Gupalo\MonologDbalLogger\EasyAdmin\Controller\Traits;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;

/**
 * @method iterable<FieldInterface> doConfigureFields(string $pageName)
 */
trait ReadOnlyCrudControllerTrait
{
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->add(Crud::PAGE_INDEX, Action::EDIT)
            ->add(Crud::PAGE_EDIT, Action::INDEX)
            ->disable(Action::DELETE, Action::NEW, Action::SAVE_AND_CONTINUE, Action::SAVE_AND_RETURN)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        $items = $this->doConfigureFields($pageName);
        foreach ($items as $item) {
            if (method_exists($item, 'setDisabled')) {
                $item->setDisabled();
            }
            if (method_exists($item, 'setRequired')) {
                $item->setRequired(false);
            }

            yield $item;
        }
    }
}
