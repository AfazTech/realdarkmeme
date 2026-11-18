@if ($paginator->hasPages())
<nav role="navigation" aria-label="Pagination">
 @if ($paginator->onFirstPage())<span class="disabled">Previous</span>@else<a href="{{ $paginator->previousPageUrl() }}">Previous</a>@endif
 <span class="pager-info">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
 @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}">Next</a>@else<span class="disabled">Next</span>@endif
</nav>
@endif
