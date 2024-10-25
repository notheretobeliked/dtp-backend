<div class="{{ $block->classes }} grid grid-cols-2" style="{{ $block->inlineStyle }}">
  <div>
    <InnerBlocks template="{{ $block->template }}" />
  </div>
  <ul>
    @foreach ($images as $image)
      <li>{{ $image['src'] }}</li>
    @endforeach
  </ul>
</div>
