<?php

namespace App\Fields;

use Log1x\AcfComposer\Builder;
use Log1x\AcfComposer\Field;

class PageDesign extends Field
{
    /**
     * The field group.
     */
    public function fields(): array
    {
        $pageDesign = Builder::make('page_design');

        $pageDesign
            ->setLocation('post_type', '==', 'page');
            

        $pageDesign
            ->addField('bg_colour', 'editor_palette')
            ->setConfig('return_format', 'object'); // Can be 'slug', 'name', or 'hex'



        return $pageDesign->build();
    }
}
