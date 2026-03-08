<div @if($page->id) data-page-id="{{ $page->id }}" @endif>
    @foreach($sections as $section)
        @include('layup::components.section', ['section' => $section])
    @endforeach
</div>
@layupScripts