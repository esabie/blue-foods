<nav class="flex flex-wrap gap-x-4 gap-y-1 text-sm font-medium text-sky-800" aria-label="Help pages">
    <a href="{{ route('site.faq') }}" @class(['underline underline-offset-4 decoration-2' => request()->routeIs('site.faq'), 'hover:underline' => ! request()->routeIs('site.faq')])>FAQ</a>
    <a href="{{ route('site.privacy') }}" @class(['underline underline-offset-4 decoration-2' => request()->routeIs('site.privacy'), 'hover:underline' => ! request()->routeIs('site.privacy')])>Privacy</a>
    <a href="{{ route('site.terms') }}" @class(['underline underline-offset-4 decoration-2' => request()->routeIs('site.terms'), 'hover:underline' => ! request()->routeIs('site.terms')])>Terms</a>
</nav>
