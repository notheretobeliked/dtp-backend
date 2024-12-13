<?php

namespace App\Fields;

use Log1x\AcfComposer\Builder;
use Log1x\AcfComposer\Field;

class LearningHubFields extends Field
{
    /**
     * The field group.
     */
    public function fields(): array
    {
        $fields = Builder::make('learning_hub_fields');

        $fields
            ->setLocation('post_type', '==', 'post');

        $fields
            ->addText('byline');

        return $fields->build();
    }
}
