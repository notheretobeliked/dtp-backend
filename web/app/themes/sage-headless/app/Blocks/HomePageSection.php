<?php

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class HomePageSection extends Block
{
    /**
     * The block name.
     *
     * @var string
     */
    public $name = 'Home Page Section';

    /**
     * The block description.
     *
     * @var string
     */
    public $description = 'A block for the homepage.';

    /**
     * The block category.
     *
     * @var string
     */
    public $category = 'DTP blocks';

    /**
     * The block icon.
     *
     * @var string|array
     */
    public $icon = 'editor-gallery';

    /**
     * The block keywords.
     *
     * @var array
     */
    public $keywords = [];

    /**
     * The block post type allow list.
     *
     * @var array
     */
    public $post_types = [];

    /**
     * The parent block type allow list.
     *
     * @var array
     */
    public $parent = [];

    /**
     * The ancestor block type allow list.
     *
     * @var array
     */
    public $ancestor = [];

    /**
     * The default block mode.
     *
     * @var string
     */
    public $mode = 'preview';

    /**
     * The default block alignment.
     *
     * @var string
     */
    public $align = '';

    /**
     * The default block text alignment.
     *
     * @var string
     */
    public $align_text = '';

    /**
     * The default block content alignment.
     *
     * @var string
     */
    public $align_content = '';

    /**
     * The supported block features.
     *
     * @var array
     */
    public $supports = [
        'align' => true,
        'align_text' => false,
        'align_content' => false,
        'full_height' => false,
        'anchor' => false,
        'mode' => true,
        'multiple' => true,
        'jsx' => true,
        'color' => [
            'background' => true,
            'text' => true,
            'gradient' => true,
        ],
    ];

    /**
     * The block template.
     *
     * @var array
     */
    public $template = [
        'core/heading' => [
            'level' => 2,
            'placeholder' => 'عنوان الفصل',
            'style' => [
                'typography' => [
                    'fontFamily' => 'custom-font-family'
                ],
                'direction' => 'rtl'
            ]
        ],
        'core/heading' => ['level' => 2, 'placeholder' => 'Chapter Title'],
    ];

    /**
     * Data to be passed to the block before rendering.
     */
    public function with(): array
    {
        return [
            'images' => $this->images(),
        ];
    }

    /**
     * The block field group.
     */
    public function fields(): array
    {
        $homePageSection = Builder::make('home_page_section');

        $homePageSection
            ->addGallery('images');

        return $homePageSection->build();
    }

    /**
     * Retrieve the items.
     *
     * @return array
     */
    public function images()
    {
        return get_field('images');
    }

    /**
     * Assets enqueued when rendering the block.
     */
    public function assets(array $block): void
    {
        //
    }
}
