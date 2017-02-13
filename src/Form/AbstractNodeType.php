<?php
declare(strict_types = 1);

namespace Gdbots\Bundle\NcrBundle\Form;

use Gdbots\Bundle\PbjxBundle\Form\AbstractPbjType;

abstract class AbstractNodeType extends AbstractPbjType
{
    /**
     * {@inheritdoc}
     */
    protected function getIgnoredFields(): array
    {
        return [
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
        return ['_id', 'status', 'etag'];
    }
}
