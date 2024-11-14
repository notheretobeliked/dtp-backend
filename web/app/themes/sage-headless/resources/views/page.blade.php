@extends('layouts.app')

@section('content')
@dump(get_field('bg_colour'))
  @while(have_posts()) @php(the_post())
    @include('partials.page-header')
    @includeFirst(['partials.content-page', 'partials.content'])
  @endwhile
@endsection
