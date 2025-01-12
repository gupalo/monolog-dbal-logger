<?php

namespace Gupalo\MonologDbalLogger\EasyAdmin\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Gupalo\MonologDbalLogger\EasyAdmin\Controller\Helpers\CrudField;
use Gupalo\MonologDbalLogger\EasyAdmin\Controller\Traits\CrudControllerTrait;
use Gupalo\MonologDbalLogger\EasyAdmin\Controller\Traits\ReadOnlyCrudControllerTrait;
use Gupalo\MonologDbalLogger\EasyAdmin\Field\YamlField;
use Gupalo\MonologDbalLogger\Entity\Log;

class LogCrudController extends AbstractCrudController
{
    use CrudControllerTrait;
    use ReadOnlyCrudControllerTrait;

    public static function getEntityFqcn(): string
    {
        return Log::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $this->_configureCrud($crud)
            ->setSearchFields(['message'])
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return parent::configureFilters($filters)
            ->add('id')
            ->add('message')
            ->add('level')
            ->add('context')
            ->add(TextFilter::new('channel')->setLabel('Channel'))
            ->add(TextFilter::new('cmd')->setLabel('Command'))
            ->add('method')
            ->add('uid')
            ->add('count')
            ->add('time')
            ->add('exceptionClass')
            ->add('exceptionMessage')
            ->add('exceptionLine')
            ->add('exceptionTrace')
            ->add('createdAt')
        ;
    }

    public function doConfigureFields(string $pageName): iterable
    {
        $isIndex = (Crud::PAGE_INDEX === $pageName);

        yield CrudField::panel('Message', 8);
        yield IdField::new('id')->hideOnForm();
        yield CrudField::createdAt(3);
        yield TextField::new('levelName')->setLabel('Level')->setColumns(3)
            ->formatValue(fn (?string $level) => $this->formatLevel($level));
        yield IntegerField::new('level')->setLabel('Level Code')->setColumns(3)->hideOnIndex();
        yield TextField::new('channel')->setLabel('Channel')->setColumns(3);
        yield TextField::new('message')->setColumns(12)->setMaxLength(70);
        yield YamlField::new('context')->hideOnIndex()->setColumns(12);

        yield CrudField::virtual('Fields', fn ($d, Log $v) => $this->formatFields($v));
        yield CrudField::virtual('Context', fn ($d, Log $v) => $this->formatContext($v->getContext()));

        yield CrudField::panel('Fields', 4);
        yield TextField::new('method')->setColumns(12)->hideOnIndex();
        yield TextField::new('cmd')->setColumns(6)->hideOnIndex();
        yield TextField::new('uid')->setColumns(6)->hideOnIndex();
        yield IntegerField::new('count')->setColumns(6)->hideOnIndex();
        yield NumberField::new('time')->setColumns(6)->hideOnIndex();


        yield CrudField::panel('Exception');
        yield TextField::new('exceptionMessage')->hideOnIndex()->setColumns(12);
        yield TextField::new('exceptionClass')->hideOnIndex()->setColumns(3);
        yield TextField::new('exceptionLine')->hideOnIndex()->setColumns(9);
        yield TextareaField::new('exceptionTrace')->hideOnIndex()->setColumns(12);
    }

    private function formatFields(Log $v): string
    {
        $result = [];
        if ($v->getCmd()) {
            $result[] = sprintf('<small class="text-muted">cmd: </small>%s', $v->getCmd());
        }
        if ($v->getMethod()) {
            $result[] = sprintf('<small class="text-muted">method: </small>%s', $v->getMethod());
        }
        if ($v->getUid()) {
            $result[] = sprintf('<small class="text-muted">uid: </small>%s', $v->getUid());
        }
        if ($v->getCount()) {
            $result[] = sprintf('<small class="text-muted">count: </small>%s', $v->getCount());
        }
        if ($v->getTime()) {
            $result[] = sprintf('<small class="text-muted">time: </small>%s', number_format($v->getTime(), 3));
        }
        if ($v->getExceptionClass()) {
            $result[] = sprintf('<small class="text-muted">exc.: </small>%s', preg_replace('#^.*\\\\#', '', $v->getExceptionClass()));
        }
        if (!$result) {
            return ' ';
        }

        return implode('<br/>', $result);
    }

    private function formatLevel(?string $name): string
    {
        if (!$name) {
            return ' ';
        }

        $class = match ($name) {
            'emergency', 'alert', 'critical', 'error' => 'danger',
            'warning' => 'warning',
            'notice' => 'info',
            'info' => 'dark',
            'debug' => 'light',
            default => 'secondary',
        };

        return sprintf('<span title="%s" class="badge text-bg-%s">%s</span>', $name, $class, mb_strtoupper($name));
    }

    private function formatContext(?array $v): string
    {
        if (!$v) {
            return ' ';
        }

        $keys = array_keys($v);
        $countKeys = count($keys);
        $maxKeys = 5;
        if ($countKeys > $maxKeys) {
            $keys = array_slice($keys, 0, $maxKeys);
            $keys[] = sprintf('<span class="badge text-bg-dark">+%s</span>', $countKeys - $maxKeys);
        }

        return sprintf('<small>%s</small>', implode(', ', $keys));
    }
}
