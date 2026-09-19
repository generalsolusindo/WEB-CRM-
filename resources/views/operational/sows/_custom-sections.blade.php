@foreach($customAfter($after) as $section)
    <h2>{{ $nextNumber() }}. {{ mb_strtoupper($section['title']) }}</h2>
    <p class="body-text">{{ $dots($section['content'] ?? null, 40) }}</p>
@endforeach
