<?php

declare(strict_types=1);

namespace Gupalo\MonologDbalLogger\EasyAdmin\Controller\Traits;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

trait CrudControllerTrait
{
    protected function _configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setDateFormat('yyyy-MM-dd')
            ->setDateTimeFormat('yyyy-MM-dd HH:mm:ss')
            ->renderContentMaximized()
            ->setPaginatorPageSize(100)
            ->showEntityActionsInlined()
            ->setDefaultSort(['id' => 'DESC'])
        ;
    }
}
