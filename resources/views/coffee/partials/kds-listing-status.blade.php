{{-- Whether this metadata row's product is on the active KDS allow-list. --}}
@if($row->kds_listed === false)
    <span class="ml-2 inline-flex items-center gap-1 align-middle">
        <span class="px-2 py-0.5 text-xs font-medium rounded bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200"
              title="Not on the KDS product list: ticket lines for this product are dropped">
            Not on KDS
        </span>
        <button type="button" onclick="listOnKds({{ $row->id }})"
            class="px-2 py-0.5 text-xs font-medium rounded bg-red-600 text-white hover:bg-red-700">
            Add to KDS
        </button>
    </span>
@elseif($row->kds_listed === null)
    <span class="ml-2 px-2 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 align-middle"
          title="No matching product in the POS, so it can never be sent to the KDS">
        Not in POS
    </span>
@else
    <span class="ml-2 inline-block w-2 h-2 rounded-full bg-green-500 align-middle" title="On the KDS product list"></span>
@endif
