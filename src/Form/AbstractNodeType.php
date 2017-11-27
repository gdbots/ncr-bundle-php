<?php
declare(strict_types=1);

namespace Gdbots\Bundle\NcrBundle\Form;

use Gdbots\Bundle\PbjxBundle\Form\AbstractPbjType;

/**
 * @deprecated All form functionality moving to front end (react/angular/etc.)
 */
abstract class AbstractNodeType extends AbstractPbjType
{
    /**
     * {@inheritdoc}
     */
    protected function getIgnoredFields(): array
    {
        return [
            '_id',
            'created_at',
            'creator_ref',
            'updated_at',
            'updater_ref',
            'last_event_ref',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getHiddenFields(): array
    {
        return ['status', 'etag'];
    }
}
