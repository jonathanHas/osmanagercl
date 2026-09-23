@php
    // Picker options grouped by family label, preserving BADGE_KINDS order.
    $badgeKindsByGroup = collect($badgeKinds)->groupBy('group', preserveKeys: true)->map(fn ($kinds) => $kinds->keys());
@endphp

<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Coffee KDS - Product Metadata
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if($unlisted->count() > 0)
            <!-- Products with metadata that the KDS importer will drop -->
            <div id="kds-unlisted-banner" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-red-800 dark:text-red-200">
                                {{ $unlisted->count() }} {{ Str::plural('product', $unlisted->count()) }} will not appear on the KDS
                            </h3>
                            <p class="text-sm text-red-700 dark:text-red-300 mt-1">
                                These have metadata but are not on the KDS product list, so their ticket lines are dropped before an order is created.
                            </p>
                            <ul class="mt-3 text-sm space-y-1">
                                @foreach($unlisted as $row)
                                <li class="flex items-center gap-2">
                                    <span class="inline-block w-2 h-2 rounded-full bg-red-500"></span>
                                    <span>{{ $row->product_name }}</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">({{ $row->type === 'coffee' ? 'coffee type' : 'option' }})</span>
                                </li>
                                @endforeach
                            </ul>
                        </div>
                        <button type="button" onclick="syncKds()"
                            class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded hover:bg-red-700">
                            Add all to KDS
                        </button>
                    </div>
                </div>
            </div>
            @endif

            <!-- Coffee Types Section -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-4">Coffee Types (Main Drinks) - {{ $coffeeTypes->count() }} items</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                        Product Name
                                    </th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                        Type
                                    </th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                        Short Name (KDS Display)
                                    </th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                        Display Order
                                    </th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                        Active
                                    </th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($coffeeTypes as $coffee)
                                <tr class="bg-green-50 dark:bg-green-900/20">
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        {{ $coffee->product_name }}
                                        @include('coffee.partials.kds-listing-status', ['row' => $coffee])
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <select id="type_{{ $coffee->id }}" 
                                            class="text-sm px-2 py-1 border rounded w-24 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            onchange="handleTypeChange({{ $coffee->id }})">
                                            <option value="coffee" {{ $coffee->type === 'coffee' ? 'selected' : '' }}>Coffee</option>
                                            <option value="option" {{ $coffee->type === 'option' ? 'selected' : '' }}>Option</option>
                                        </select>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <input type="text" 
                                            id="short_name_{{ $coffee->id }}"
                                            value="{{ $coffee->short_name }}" 
                                            class="text-sm px-2 py-1 border rounded w-32 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            maxlength="20">
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <input type="number" 
                                            id="display_order_{{ $coffee->id }}"
                                            value="{{ $coffee->display_order }}" 
                                            class="text-sm px-2 py-1 border rounded w-20 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            min="0">
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <input type="checkbox" 
                                            id="is_active_{{ $coffee->id }}"
                                            {{ $coffee->is_active ? 'checked' : '' }}
                                            class="rounded dark:bg-gray-700 dark:border-gray-600">
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <div class="flex gap-2">
                                            <button onclick="updateMetadata({{ $coffee->id }})" 
                                                class="px-3 py-1 bg-blue-500 text-white text-sm rounded hover:bg-blue-600">
                                                Update
                                            </button>
                                            <button onclick="deleteMetadata({{ $coffee->id }})" 
                                                class="px-3 py-1 bg-red-500 text-white text-sm rounded hover:bg-red-600">
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Options Section -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-4">Coffee Options (Modifiers)</h3>
                    @foreach($optionsGrouped as $groupName => $options)
                    <div class="mb-6">
                        <h4 class="text-md font-medium mb-2 text-gray-700 dark:text-gray-300">{{ $groupName }}</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                            Product Name
                                        </th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                            Type
                                        </th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                            Short Name
                                        </th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                            Group
                                        </th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                            Badge
                                        </th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                            Order
                                        </th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                            Active
                                        </th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($options as $option)
                                    <tr class="bg-blue-50 dark:bg-blue-900/20">
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ $option->product_name }}
                                            @include('coffee.partials.kds-listing-status', ['row' => $option])
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <select id="type_{{ $option->id }}" 
                                                class="text-sm px-2 py-1 border rounded w-24 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                                onchange="handleTypeChange({{ $option->id }})">
                                                <option value="coffee" {{ $option->type === 'coffee' ? 'selected' : '' }}>Coffee</option>
                                                <option value="option" {{ $option->type === 'option' ? 'selected' : '' }}>Option</option>
                                            </select>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <input type="text" 
                                                id="short_name_{{ $option->id }}"
                                                value="{{ $option->short_name }}" 
                                                class="text-sm px-2 py-1 border rounded w-32 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                                maxlength="20"
                                                oninput="previewBadge({{ $option->id }})">
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <input type="text" 
                                                id="group_name_{{ $option->id }}"
                                                list="existing_group_names"
                                                value="{{ $option->group_name }}" 
                                                class="text-sm px-2 py-1 border rounded w-24 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                                maxlength="50">
                                        </td>
                                        {{-- Badge: which modifier shape this option gets on the KDS card. --}}
                                        <td class="px-4 pt-3 pb-2 whitespace-nowrap text-sm">
                                            <div class="flex items-center gap-3">
                                                <select id="badge_kind_{{ $option->id }}"
                                                    class="text-sm px-2 py-1 border rounded w-32 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                                    onchange="previewBadge({{ $option->id }})">
                                                    <option value="">Plain chip</option>
                                                    @foreach($badgeKindsByGroup as $group => $kinds)
                                                        <optgroup label="{{ $group }}">
                                                            @foreach($kinds as $kind)
                                                                <option value="{{ $kind }}" {{ $option->badge_kind === $kind ? 'selected' : '' }}>{{ ucfirst($kind) }}</option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endforeach
                                                </select>
                                                <span id="badge_preview_{{ $option->id }}"><x-kds.modifier-badge :kind="$option->badge_kind" :label="$option->short_name" /></span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <input type="number" 
                                                id="display_order_{{ $option->id }}"
                                                value="{{ $option->display_order }}" 
                                                class="text-sm px-2 py-1 border rounded w-20 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                                min="0">
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <input type="checkbox" 
                                                id="is_active_{{ $option->id }}"
                                                {{ $option->is_active ? 'checked' : '' }}
                                                class="rounded dark:bg-gray-700 dark:border-gray-600">
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <div class="flex gap-2">
                                                <button onclick="updateMetadata({{ $option->id }})" 
                                                    class="px-3 py-1 bg-blue-500 text-white text-sm rounded hover:bg-blue-600">
                                                    Update
                                                </button>
                                                <button onclick="deleteMetadata({{ $option->id }})" 
                                                    class="px-3 py-1 bg-red-500 text-white text-sm rounded hover:bg-red-600">
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Missing Metadata Section -->
            @if($missingMetadata->count() > 0)
            <div class="bg-yellow-50 dark:bg-yellow-900/20 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-4 text-yellow-800 dark:text-yellow-200">
                        Products Missing Metadata ({{ $missingMetadata->count() }})
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($missingMetadata as $product)
                        <div class="bg-white dark:bg-gray-800 p-4 rounded border">
                            <p class="font-medium">{{ $product->NAME }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">ID: {{ $product->ID }}</p>
                            <button type="button" onclick="openCreateMetadata(@js($product->ID), @js($product->NAME), @js($product->kds_name))" 
                                class="mt-2 px-3 py-1 bg-green-500 text-white text-sm rounded hover:bg-green-600">
                                Add Metadata
                            </button>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>


    <datalist id="existing_group_names">
        @foreach($groupNames as $groupName)
        <option value="{{ $groupName }}"></option>
        @endforeach
    </datalist>

    <!-- Create Metadata Modal -->
    <div x-data="createMetadataModal(@js($groupNames), @js($sampleDrinkName))"
         x-on:open-create-metadata.window="open($event.detail.productId, $event.detail.productName, $event.detail.kdsName)"
         x-on:keydown.escape.window="close()"
         x-show="isOpen"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div x-on:click.outside="close()" class="w-full max-w-3xl bg-white dark:bg-gray-800 rounded-lg shadow-xl">
            <form x-on:submit.prevent="submit()" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6 text-gray-900 dark:text-gray-100">
            <div class="space-y-4">
                <div>
                    <h3 class="text-lg font-semibold">Add Metadata</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400" x-text="productName"></p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1" for="create_short_name">Short Name (KDS Display)</label>
                    <input id="create_short_name" x-ref="shortName" x-model="shortName" type="text" maxlength="20" required
                        class="w-full text-sm px-2 py-1 border rounded dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                </div>

                <div>
                    <span class="block text-sm font-medium mb-1">Type</span>
                    <div class="flex gap-4 text-sm">
                        <label class="inline-flex items-center gap-1">
                            <input type="radio" value="coffee" x-model="type" class="dark:bg-gray-700 dark:border-gray-600">
                            Coffee type (main drink)
                        </label>
                        <label class="inline-flex items-center gap-1">
                            <input type="radio" value="option" x-model="type" class="dark:bg-gray-700 dark:border-gray-600">
                            Option / modifier
                        </label>
                    </div>
                </div>

                <div x-show="type === 'option'" class="space-y-2">
                    <label class="block text-sm font-medium mb-1" for="create_group_select">Group</label>
                    <select id="create_group_select" x-model="groupSelection"
                        class="w-full text-sm px-2 py-1 border rounded dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                        <template x-for="group in existingGroups" :key="group">
                            <option :value="group" x-text="group"></option>
                        </template>
                        <option value="__new__">+ New group…</option>
                    </select>
                    <input x-show="groupSelection === '__new__'" x-ref="newGroup" x-model="newGroupName" type="text" maxlength="50"
                        placeholder="New group name (e.g. Syrups, Milk, Service)"
                        class="w-full text-sm px-2 py-1 border rounded dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">

                    <label class="block text-sm font-medium mb-1 pt-2" for="create_badge_kind">Badge</label>
                    <select id="create_badge_kind" x-model="badgeKind"
                        class="w-full text-sm px-2 py-1 border rounded dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                        <option value="">Plain chip</option>
                        @foreach($badgeKindsByGroup as $group => $kinds)
                            <optgroup label="{{ $group }}">
                                @foreach($kinds as $kind)
                                    <option value="{{ $kind }}">{{ ucfirst($kind) }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1" for="create_display_order">Display Order</label>
                    <input id="create_display_order" x-model.number="displayOrder" type="number" min="0" required
                        class="w-32 text-sm px-2 py-1 border rounded dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                </div>

                <p x-show="error" x-text="error" class="text-sm text-red-600 dark:text-red-400"></p>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" x-on:click="close()"
                        class="px-3 py-1 text-sm rounded border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700">
                        Cancel
                    </button>
                    <button type="submit" :disabled="saving"
                        class="px-3 py-1 bg-green-500 text-white text-sm rounded hover:bg-green-600 disabled:opacity-50">
                        <span x-text="saving ? 'Saving…' : 'Create'"></span>
                    </button>
                </div>
            </div>

            <!-- Live KDS preview: mirrors the /kds card markup and theme -->
            <div class="space-y-2">
                <span class="block text-sm font-medium">KDS preview</span>
                <div class="kds-preview">
                    <div class="kds-preview__group">
                        <span class="kds-preview__dot"></span>
                        <span class="kds-preview__label">Drinks</span>
                        <span class="kds-preview__count" x-text="type === 'option' ? '1' : '2'"></span>
                    </div>
                    <ul class="kds-preview__list">
                        <li class="kds-preview__item">
                            <span class="kds-preview__check"></span>
                            <div class="kds-preview__main">
                                <span class="kds-preview__qty">1×</span>
                                <span class="kds-preview__name" x-text="type === 'option' ? sampleDrinkName : kdsName"></span>
                                <template x-if="type === 'option'">
                                    <span class="kds-preview__mods">
                                        <span x-html="kdsModifierBadgeHtml(badgeKind, shortName.trim() || '…')"></span>
                                    </span>
                                </template>
                            </div>
                        </li>
                        <li class="kds-preview__item" x-show="type !== 'option'">
                            <span class="kds-preview__check"></span>
                            <div class="kds-preview__main">
                                <span class="kds-preview__qty">1×</span>
                                <span class="kds-preview__name" x-text="sampleDrinkName"></span>
                            </div>
                        </li>
                    </ul>
                </div>
                <p class="text-xs text-gray-600 dark:text-gray-400" x-show="type === 'option'">
                    Options fold into the drink above them showing the <strong>short name</strong>. The group is only used to organise this page; the KDS never shows it.
                    Pick a badge to give this option its own shape and colour on the KDS. Leave it as <strong>Plain chip</strong> for service options like Takeaway.
                </p>
                <p class="text-xs text-gray-600 dark:text-gray-400" x-show="type !== 'option'">
                    Drink lines show the product's POS display name, so the short name has no effect on the KDS card for coffee types.
                </p>
            </div>
            </form>
        </div>
    </div>

    <style>
        .kds-preview {
            --kp-bg: #fbf7f0; --kp-bg2: #f3ede2; --kp-panel: #ffffff; --kp-line: #ece4d4; --kp-line2: #ddd2bd;
            --kp-ink: #1c1714; --kp-accent: #ba6531; --kp-accent-fg: #ffffff; --kp-drink: #5b3a26;
            background: var(--kp-panel);
            color: var(--kp-ink);
            border: 1px solid var(--kp-line);
            border-radius: 12px;
            padding: 10px 12px;
            font-family: 'Geist', system-ui, -apple-system, sans-serif;
        }
        .kds-preview__group {
            display: flex; align-items: center; gap: 6px;
            padding: 2px 0 6px;
            font-size: 11px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--kp-drink);
        }
        .kds-preview__dot { width: 7px; height: 7px; border-radius: 999px; background: var(--kp-drink); }
        .kds-preview__count { margin-left: auto; font-family: ui-monospace, monospace; font-size: 11px; color: var(--kp-ink); background: var(--kp-bg2); padding: 1px 7px; border-radius: 999px; }
        .kds-preview__list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 2px; }
        .kds-preview__item { display: flex; align-items: flex-start; gap: 10px; padding: 8px 4px 8px 0; }
        .kds-preview__check { width: 24px; height: 24px; border-radius: 7px; border: 1.5px solid var(--kp-line2); flex-shrink: 0; margin-top: 1px; }
        .kds-preview__main { display: flex; align-items: center; flex-wrap: wrap; gap: 6px 8px; font-size: 16px; line-height: 1.2; }
        .kds-preview__qty { font-family: ui-monospace, monospace; font-weight: 600; font-variant-numeric: tabular-nums; min-width: 26px; }
        .kds-preview__name { font-weight: 600; letter-spacing: -0.01em; }
        .kds-preview__mods { display: inline-flex; align-items: center; flex-wrap: wrap; gap: 5px 6px; }
        .kds-preview__mod {
            font-size: 14px; padding: 3px 10px; border-radius: 6px;
            background: var(--kp-accent); color: var(--kp-accent-fg);
            font-weight: 700; letter-spacing: -0.01em; border: 1px solid var(--kp-accent); white-space: nowrap;
        }
        /* Options with no badge render as .item__mod (both the component and
           kdsModifierBadgeHtml emit it). /kds styles that class itself; this
           page has to, for the table previews and the modal preview alike.
           Literal colours because --kp-accent is scoped to .kds-preview. */
        .item__mod {
            display: inline-block;
            font-size: 14px; padding: 3px 10px; border-radius: 6px;
            background: #ba6531; color: #ffffff;
            font-weight: 700; letter-spacing: -0.01em; border: 1px solid #ba6531; white-space: nowrap;
            font-family: 'Geist', system-ui, -apple-system, sans-serif;
        }
        /* Room for the badge decorations that overhang the badge box. */
        .kds-preview__mods { min-height: 2.6em; }
    </style>

    {{-- Geist for the KDS preview, Public Sans 800 for modifier badge labels --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;600;700&family=Public+Sans:wght@800&display=swap" rel="stylesheet">

    @include('kds._modifier-badge-assets')

    @push('scripts')
    <script>
        async function updateMetadata(id) {
            const shortName = document.getElementById(`short_name_${id}`).value;
            const displayOrder = document.getElementById(`display_order_${id}`).value;
            const isActive = document.getElementById(`is_active_${id}`).checked;
            const type = document.getElementById(`type_${id}`).value;
            
            let groupName = null;
            if (type === 'option') {
                const groupElement = document.getElementById(`group_name_${id}`);
                if (groupElement) {
                    groupName = groupElement.value;
                }
            }

            // Only option rows carry a badge picker.
            const badgeElement = document.getElementById(`badge_kind_${id}`);
            const badgeKind = badgeElement ? (badgeElement.value || null) : null;

            try {
                const response = await fetch(`/coffee/metadata/${id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        short_name: shortName,
                        type: type,
                        group_name: groupName,
                        badge_kind: badgeKind,
                        display_order: parseInt(displayOrder),
                        is_active: isActive
                    })
                });

                const result = await response.json();
                if (result.success) {
                    // Show success feedback
                    const button = event.target;
                    const originalText = button.textContent;
                    button.textContent = '✓ Updated';
                    button.className = button.className.replace('bg-blue-500 hover:bg-blue-600', 'bg-green-500 hover:bg-green-600');
                    
                    setTimeout(() => {
                        button.textContent = originalText;
                        button.className = button.className.replace('bg-green-500 hover:bg-green-600', 'bg-blue-500 hover:bg-blue-600');
                    }, 2000);
                } else {
                    alert('Failed to update metadata');
                }
            } catch (error) {
                console.error('Error updating metadata:', error);
                alert('Error updating metadata');
            }
        }

        /* Live badge preview in the options table: follows the picker and the
           short name, since the label on the card is the short name. */
        function previewBadge(id) {
            const target = document.getElementById(`badge_preview_${id}`);
            const select = document.getElementById(`badge_kind_${id}`);
            if (!target || !select) return;
            const shortName = document.getElementById(`short_name_${id}`)?.value.trim() || '…';
            target.innerHTML = kdsModifierBadgeHtml(select.value, shortName);
        }

        function handleTypeChange(id) {
            const type = document.getElementById(`type_${id}`).value;
            const row = document.querySelector(`tr:has(#type_${id})`);
            
            if (type === 'coffee') {
                row.className = 'bg-green-50 dark:bg-green-900/20';
            } else {
                row.className = 'bg-blue-50 dark:bg-blue-900/20';
            }
        }

        async function deleteMetadata(id) {
            if (!confirm('Are you sure you want to delete this metadata? This cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch(`/coffee/metadata/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                const result = await response.json();
                if (result.success) {
                    // Remove the row from the table
                    const row = document.querySelector(`tr:has(#type_${id})`);
                    if (row) {
                        row.style.transition = 'opacity 0.5s';
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 500);
                    }
                } else {
                    alert('Failed to delete metadata');
                }
            } catch (error) {
                console.error('Error deleting metadata:', error);
                alert('Error deleting metadata');
            }
        }

        function openCreateMetadata(productId, productName, kdsName) {
            window.dispatchEvent(new CustomEvent('open-create-metadata', {
                detail: { productId, productName, kdsName }
            }));
        }

        function createMetadataModal(existingGroups, sampleDrinkName) {
            return {
                sampleDrinkName: sampleDrinkName,
                kdsName: '',
                isOpen: false,
                saving: false,
                error: '',
                existingGroups: existingGroups,
                productId: null,
                productName: '',
                shortName: '',
                type: 'coffee',
                groupSelection: '',
                newGroupName: '',
                badgeKind: '',
                displayOrder: 999,

                open(productId, productName, kdsName) {
                    this.productId = productId;
                    this.productName = productName;
                    this.kdsName = kdsName || productName;
                    this.shortName = productName.substring(0, 20);
                    this.type = 'coffee';
                    this.groupSelection = this.existingGroups[0] ?? '__new__';
                    this.newGroupName = '';
                    this.badgeKind = '';
                    this.displayOrder = 999;
                    this.error = '';
                    this.isOpen = true;
                    this.$nextTick(() => this.$refs.shortName.focus());
                },

                close() {
                    if (this.saving) return;
                    this.isOpen = false;
                },

                resolvedGroupName() {
                    if (this.type !== 'option') return null;
                    if (this.groupSelection === '__new__') return this.newGroupName.trim();
                    return this.groupSelection;
                },

                async submit() {
                    this.error = '';
                    const groupName = this.resolvedGroupName();
                    if (this.type === 'option' && !groupName) {
                        this.error = 'Choose an existing group or enter a new group name.';
                        this.$nextTick(() => this.$refs.newGroup.focus());
                        return;
                    }

                    this.saving = true;
                    try {
                        const response = await fetch('/coffee/metadata', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({
                                product_id: this.productId,
                                product_name: this.productName,
                                short_name: this.shortName.trim(),
                                type: this.type,
                                group_name: groupName,
                                badge_kind: this.badgeKind || null,
                                display_order: this.displayOrder
                            })
                        });

                        const result = await response.json();
                        if (response.ok && result.success) {
                            location.reload();
                            return;
                        }
                        this.error = result.message || 'Failed to create metadata';
                    } catch (error) {
                        console.error('Error creating metadata:', error);
                        this.error = 'Error creating metadata';
                    } finally {
                        this.saving = false;
                    }
                }
            };
        }

        async function listOnKds(id) {
            try {
                const response = await fetch(`/coffee/metadata/${id}/list-on-kds`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                const result = await response.json();
                if (response.ok && result.success) {
                    location.reload();
                } else {
                    alert(result.message || 'Failed to add product to the KDS');
                }
            } catch (error) {
                console.error('Error adding product to KDS:', error);
                alert('Error adding product to the KDS');
            }
        }

        async function syncKds() {
            try {
                const response = await fetch('/coffee/metadata/sync-kds', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                const result = await response.json();
                if (response.ok && result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert(result.message || 'Failed to sync products to the KDS');
                }
            } catch (error) {
                console.error('Error syncing products to KDS:', error);
                alert('Error syncing products to the KDS');
            }
        }

        async function addSpecificSyrups() {
            try {
                const response = await fetch('/coffee/metadata/add-syrups', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                const result = await response.json();
                if (result.success) {
                    alert(result.message);
                    if (result.created > 0) {
                        location.reload();
                    }
                } else {
                    alert('Failed to add syrups');
                }
            } catch (error) {
                console.error('Error adding syrups:', error);
                alert('Error adding syrups');
            }
        }
    </script>
    @endpush
</x-admin-layout>