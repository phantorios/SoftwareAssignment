<!doctype html>
<script src="https://cdn.tailwindcss.com"></script>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Equipments</title>
</head>
<body class="bg-gray-50 text-gray-900">
<div class="max-w-7xl mx-auto p-6">
    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-semibold">Equipments</h1>
            <p class="text-sm text-gray-600 mt-1">
                Search by Equipment, Material, Description, or Room, results update instantly without reloading page.
            </p>
        </div>

        <div class="text-sm text-gray-600">
            <span id="resultCount"></span>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <label for="q" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
        <div class="flex items-center gap-3">
            <input
                id="q"
                type="text"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                placeholder="search by Equipment, Material, Description, or Room..."
                autocomplete="off"
            />
            <button
                id="clearBtn"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50"
                type="button"
            >
                Clear
            </button>
        </div>

        <div id="status" class="mt-2 text-xs text-gray-500"></div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-100">
                <tr>
                    @foreach($headers as $header)
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase">
                            {{ $header }}
                        </th>
                    @endforeach
                </tr>
                </thead>
                <tbody id="tbody" class="divide-y divide-gray-100">
                @foreach($initialPage->items() as $row)
                    <tr class="hover:bg-gray-50">
                        @foreach($headers as $h)
                            <td class="px-3 py-2 whitespace-nowrap text-sm">
                                {{ data_get($row, $h) }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="flex items-center justify-between gap-4 p-4 border-t border-gray-200">
                <div id="pagerInfo" class="text-sm text-gray-600"></div>

                <div class="flex items-center gap-2">
                    <button id="prevBtn" class="px-3 py-2 text-sm border rounded-lg hover:bg-gray-50" type="button">
                        Prev
                    </button>

                    <div id="pageButtons" class="flex items-center gap-1"></div>

                    <button id="nextBtn" class="px-3 py-2 text-sm border rounded-lg hover:bg-gray-50" type="button">
                        Next
                    </button>
                </div>
            </div>
        </div>

        <div id="emptyState" class="hidden p-6 text-sm text-gray-600">
            No results.
        </div>
    </div>
</div>

<script>
    (() => {
        const input = document.getElementById('q');
        const tbody = document.getElementById('tbody');
        const status = document.getElementById('status');
        const clearBtn = document.getElementById('clearBtn');
        const emptyState = document.getElementById('emptyState');
        const resultCount = document.getElementById('resultCount');

        const pagerInfo = document.getElementById('pagerInfo');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const pageButtons = document.getElementById('pageButtons');

        const headers = @json($headers);

        let timer = null;
        let controller = null;

        let state = {
            q: '',
            page: {{ $initialPage->currentPage() }},
            lastPage: {{ $initialPage->lastPage() }},
            total: {{ $initialPage->total() }},
            from: {{ $initialPage->firstItem() ?? 0 }},
            to: {{ $initialPage->lastItem() ?? 0 }},
        };

        function escapeHtml(s) {
            return String(s ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function renderRows(rows) {
            tbody.innerHTML = '';

            if (!rows || rows.length === 0) {
                emptyState.classList.remove('hidden');
                return;
            }

            emptyState.classList.add('hidden');

            let html = '';

            for (const row of rows) {
                html += '<tr class="hover:bg-gray-50">';
                for (const h of headers) {
                    html += `<td class="px-3 py-2 whitespace-nowrap text-sm">${escapeHtml(row[h])}</td>`;
                }
                html += '</tr>';
            }

            tbody.innerHTML = html;
        }

        function renderPager() {
            // info
            const from = state.from ?? 0;
            const to = state.to ?? 0;
            const total = state.total ?? 0;

            pagerInfo.textContent = total === 0
                ? '0 results'
                : `Showing ${from}-${to} of ${total}`;

            resultCount.textContent = `${total} total`;

            // prev/next enable
            prevBtn.disabled = state.page <= 1;
            nextBtn.disabled = state.page >= state.lastPage;

            prevBtn.classList.toggle('opacity-50', prevBtn.disabled);
            prevBtn.classList.toggle('cursor-not-allowed', prevBtn.disabled);

            nextBtn.classList.toggle('opacity-50', nextBtn.disabled);
            nextBtn.classList.toggle('cursor-not-allowed', nextBtn.disabled);

            // page buttons (simple window)
            pageButtons.innerHTML = '';
            const windowSize = 5;
            const start = Math.max(1, state.page - Math.floor(windowSize / 2));
            const end = Math.min(state.lastPage, start + windowSize - 1);

            for (let p = start; p <= end; p++) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = p;
                btn.className = 'px-3 py-2 text-sm border rounded-lg hover:bg-gray-50';
                if (p === state.page) {
                    btn.className += ' bg-gray-100 font-semibold';
                }
                btn.addEventListener('click', () => goToPage(p));
                pageButtons.appendChild(btn);
            }
        }

        async function fetchPage(q, page) {
            if (controller) controller.abort();
            controller = new AbortController();

            const url = new URL("{{ route('equipments.search') }}", window.location.origin);
            if (q && q.trim() !== '') url.searchParams.set('q', q.trim());
            url.searchParams.set('page', String(page));

            status.textContent = 'Loading…';

            const res = await fetch(url.toString(), {
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                signal: controller.signal
            });

            if (!res.ok) {
                status.textContent = `Failed (HTTP ${res.status})`;
                return;
            }

            const json = await res.json();

            renderRows(json.data);

            state.page = json.meta.current_page;
            state.lastPage = json.meta.last_page;
            state.total = json.meta.total;
            state.from = json.meta.from;
            state.to = json.meta.to;

            renderPager();

            status.textContent = q ? `Search: "${q}"` : 'Showing latest records';
        }

        function goToPage(p) {
            state.page = p;
            fetchPage(state.q, state.page);
        }

        function debounceSearch() {
            if (timer) clearTimeout(timer);
            timer = setTimeout(() => {
                state.q = input.value.trim();
                state.page = 1; // reset to first page on new search
                fetchPage(state.q, state.page);
            }, 250);
        }

        input.addEventListener('input', debounceSearch);

        clearBtn.addEventListener('click', () => {
            input.value = '';
            state.q = '';
            state.page = 1;
            fetchPage(state.q, state.page);
            input.focus();
        });

        prevBtn.addEventListener('click', () => {
            if (state.page > 1) goToPage(state.page - 1);
        });

        nextBtn.addEventListener('click', () => {
            if (state.page < state.lastPage) goToPage(state.page + 1);
        });

        // initial pager render (server-side page)
        renderPager();
        status.textContent = 'Showing latest records';
    })();
</script>
</body>
</html>
