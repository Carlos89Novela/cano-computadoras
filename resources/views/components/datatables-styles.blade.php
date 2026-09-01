<style>
/* DataTables + Tailwind-like helper styles (plain CSS for browsers) */
.dataTables_wrapper .dataTables_length select,
.dataTables_wrapper .dataTables_filter input[type="search"] {
    border-radius: 0.375rem; /* rounded-md */
    background-color: #0f1724; /* bg-zinc-900 */
    border: 1px solid #27272a; /* border-zinc-700 */
    color: #ffffff;
    padding: 0.5rem 0.75rem; /* px-3 py-2 */
}
.dataTables_wrapper .dataTables_paginate a {
    margin-left: 0.25rem;
    margin-right: 0.25rem;
    display: inline-flex;
    align-items: center;
    border-radius: 0.375rem;
    background-color: transparent;
    border: 1px solid #27272a;
    padding: 0.25rem 0.75rem;
    font-size: 0.875rem;
    color: #ffffff;
    text-decoration: none;
}
.dataTables_wrapper .dataTables_paginate a.current,
.dataTables_wrapper .dataTables_paginate a:hover {
    background-color: #7c3aed; /* purple-600 */
    color: #ffffff;
    border-color: transparent;
}
</style>