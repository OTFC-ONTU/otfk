{!! '<' . '?xml version="1.0" encoding="UTF-8"?' . '>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ $siteName }} — {{ __('public.news') }}</title>
        <link>{{ \App\Support\LocalizedUrl::route('news.index') }}</link>
        <description>{{ $description }}</description>
        <language>{{ app()->getLocale() }}</language>
        <lastBuildDate>{{ now()->toRfc2822String() }}</lastBuildDate>
        <atom:link href="{{ \App\Support\LocalizedUrl::route('news.feed') }}" rel="self" type="application/rss+xml"/>
        @foreach ($news as $item)
            <item>
                <title>{{ $item->localized('title') }}</title>
                <link>{{ \App\Support\LocalizedUrl::route('news.show', $item) }}</link>
                <guid isPermaLink="true">{{ \App\Support\LocalizedUrl::route('news.show', $item) }}</guid>
                @if ($item->published_at)
                    <pubDate>{{ $item->published_at->copy()->shiftTimezone('Europe/Kyiv')->toRfc2822String() }}</pubDate>
                @endif
                @if ($item->localized('excerpt'))
                    <description>{{ $item->localized('excerpt') }}</description>
                @endif
            </item>
        @endforeach
    </channel>
</rss>
