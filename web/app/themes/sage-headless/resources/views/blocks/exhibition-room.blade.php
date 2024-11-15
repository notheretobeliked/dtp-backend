<div class="{{ $block->classes }}" data-{{ $block->name }}>
  <h1 class="text-center">{{ $name_ar }}</h1>
  <h1 class="text-center">{{ $name_en }}</h1>
  <div class="cabinet-intro">
    {!! $intro_text !!}
  </div>
  @if($cabinets)
    <div class="cabinets">
      @foreach($cabinets as $cabinet)
        <div class="cabinet">
          <h1 class="text-center">{{ $cabinet['name_ar'] }}</h1>
          <h1 class="text-center">{{ $cabinet['name_en'] }}</h1>
          <div class="cabinet-intro">
            {!! $cabinet['intro_text'] !!}
          </div>

          @if($cabinet['groups'])
            <div class="groups">
              @foreach($cabinet['groups'] as $group)
                <div class="group group--{{ $group['layout'] }}">
                  @if($group['images'])
                    <div class="images flex w-full flex-row flex-wrap">
                      @foreach($group['images'] as $image)
                        <div class="item">
                          {!! wp_get_attachment_image($image['ID'], 'thumbnail') !!}
                          <div class="reference">{{ $image['reference'] }}</div>
                        </div>
                      @endforeach
                    </div>
                  @endif
                </div>
              @endforeach
            </div>
          @endif
        </div>
      @endforeach
    </div>
  @endif
</div>