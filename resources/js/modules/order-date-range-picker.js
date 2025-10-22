import Litepicker from 'litepicker';
import 'litepicker/dist/css/litepicker.css';

const ISO_FORMAT = 'YYYY-MM-DD';
const LARGE_BREAKPOINT = 1024;
const DATE_PATTERN = '\\d{4}-\\d{2}-\\d{2}';

const parseIsoDate = (value) => {
    if (!value) {
        return null;
    }

    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
    }

    if (typeof value !== 'string') {
        return null;
    }

    const [year, month, day] = value.split('-').map((part) => Number.parseInt(part, 10));
    if ([year, month, day].some((segment) => Number.isNaN(segment))) {
        return null;
    }

    const date = new Date(year, month - 1, day);
    return Number.isNaN(date.getTime()) ? null : date;
};

const formatIsoDate = (date) => {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
        return '';
    }

    const pad = (value) => value.toString().padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
};

const ensureStartElement = (input, anchorValue) => {
    if (input) {
        if (anchorValue && !input.value) {
            input.value = anchorValue;
        }

        return input;
    }

    if (!anchorValue) {
        return null;
    }

    const hidden = document.createElement('input');
    hidden.type = 'text';
    hidden.value = anchorValue;
    hidden.setAttribute('aria-hidden', 'true');
    hidden.setAttribute('tabindex', '-1');
    hidden.classList.add('hidden');

    return hidden;
};

const enhanceForPicker = (input) => {
    if (!input || input.dataset.rangeEnhanced === '1') {
        return;
    }

    const originalType = input.getAttribute('type');
    if (originalType && originalType.toLowerCase() === 'date') {
        input.dataset.rangeOriginalType = originalType;
    }

    input.setAttribute('type', 'text');
    input.setAttribute('pattern', DATE_PATTERN);
    input.setAttribute('placeholder', input.getAttribute('placeholder') ?? 'YYYY-MM-DD');
    input.setAttribute('inputmode', 'none');
    input.dataset.rangeEnhanced = '1';
    input.readOnly = true;
};

const collectGroups = () => {
    const groups = new Map();

    document.querySelectorAll('[data-range-group]').forEach((input) => {
        const groupName = input.dataset.rangeGroup;
        if (!groupName) {
            return;
        }

        if (!groups.has(groupName)) {
            groups.set(groupName, {
                name: groupName,
                start: null,
                end: null,
                anchor: null,
                anchorFieldSelector: null,
                min: null,
            });
        }

        const entry = groups.get(groupName);
        const role = input.dataset.rangeRole === 'start' ? 'start' : 'end';

        if (role === 'start') {
            entry.start = input;
        } else {
            entry.end = input;
        }

        if (input.dataset.rangeAnchor) {
            entry.anchor = input.dataset.rangeAnchor;
        }

        if (input.dataset.rangeAnchorField) {
            entry.anchorFieldSelector = input.dataset.rangeAnchorField;
        }

        if (input.min) {
            entry.min = input.min;
        }
    });

    return groups;
};

const resolveAnchor = (entry) => {
    const { anchor, anchorFieldSelector } = entry;
    const anchorField = anchorFieldSelector ? document.querySelector(anchorFieldSelector) : null;
    const anchorValue = anchor ?? anchorField?.value ?? null;

    return { anchorValue, anchorField };
};

const setEndMin = (endElement, value) => {
    if (!endElement || !value) {
        return;
    }

    endElement.min = value;
};

const initialisePicker = (entry) => {
    const { end: endElement } = entry;
    if (!endElement) {
        return null;
    }

    const { anchorValue, anchorField } = resolveAnchor(entry);
    const startElement = ensureStartElement(entry.start, anchorValue);

    if (!startElement) {
        return null;
    }

    if (!startElement.isConnected) {
        endElement.parentNode?.insertBefore(startElement, endElement);
    }

    enhanceForPicker(startElement);
    enhanceForPicker(endElement);

    const lockStart = !entry.start;
    const initialStart = parseIsoDate(startElement.value || anchorValue);
    const initialEnd = parseIsoDate(endElement.value);
    const resolvedStart = initialStart ?? initialEnd ?? parseIsoDate(anchorValue);
    const resolvedEnd = (() => {
        if (initialEnd && resolvedStart && initialEnd.getTime() < resolvedStart.getTime()) {
            return resolvedStart;
        }

        return initialEnd ?? resolvedStart ?? null;
    })();

    const picker = new Litepicker({
        element: startElement,
        elementEnd: endElement,
        singleMode: false,
        autoApply: true,
        numberOfMonths: window.innerWidth >= LARGE_BREAKPOINT ? 2 : 1,
        format: ISO_FORMAT,
        minDate: entry.min ?? anchorValue ?? undefined,
        startDate: resolvedStart ?? anchorValue ?? null,
        endDate: resolvedEnd ?? resolvedStart ?? anchorValue ?? null,
        selectForward: true,
        resetButton: true,
        setup: (instance) => {
            instance.on('selected', (date1, date2) => {
                if (!(date1 instanceof Date)) {
                    return;
                }

                let effectiveStart = date1;
                if (lockStart) {
                    const currentAnchor = parseIsoDate(anchorField?.value ?? anchorValue ?? startElement.value);
                    if (currentAnchor) {
                        instance.setStartDate(currentAnchor, true);
                        effectiveStart = currentAnchor;
                    }
                } else {
                    startElement.value = formatIsoDate(date1);
                }

                const nextEnd = date2 instanceof Date ? date2 : effectiveStart;
                if (nextEnd.getTime() < effectiveStart.getTime()) {
                    instance.setEndDate(effectiveStart, true);
                    endElement.value = formatIsoDate(effectiveStart);
                    return;
                }

                endElement.value = formatIsoDate(nextEnd);
            });
        },
    });

    if (resolvedStart) {
        startElement.value = formatIsoDate(resolvedStart);
    } else if (anchorValue) {
        const anchorDate = parseIsoDate(anchorValue);
        if (anchorDate) {
            startElement.value = formatIsoDate(anchorDate);
        }
    }

    if (resolvedEnd) {
        endElement.value = formatIsoDate(resolvedEnd);
    }

    if (anchorValue) {
        setEndMin(endElement, formatIsoDate(parseIsoDate(anchorValue)));
    } else if (entry.min) {
        setEndMin(endElement, entry.min);
    }

    return {
        picker,
        startElement,
        endElement,
        anchorField,
        anchorValue,
        lockStart,
    };
};

const attachAnchorWatcher = (instanceData) => {
    const { anchorField, picker, startElement, endElement, lockStart } = instanceData;
    if (!anchorField) {
        return;
    }

    anchorField.addEventListener('change', () => {
        const nextAnchor = parseIsoDate(anchorField.value);
        if (!nextAnchor) {
            return;
        }

        const currentEnd = parseIsoDate(endElement.value);
        const effectiveEnd = currentEnd && currentEnd.getTime() >= nextAnchor.getTime()
            ? currentEnd
            : nextAnchor;

        startElement.value = formatIsoDate(nextAnchor);
        endElement.value = formatIsoDate(effectiveEnd);
        setEndMin(endElement, formatIsoDate(nextAnchor));

        if (lockStart) {
            picker.setDateRange(nextAnchor, effectiveEnd, true);
            return;
        }

        picker.setStartDate(nextAnchor, true);
        if (effectiveEnd) {
            picker.setEndDate(effectiveEnd, true);
        }
    });
};

export const initOrderDateRangePickers = () => {
    const groups = collectGroups();
    const instances = [];

    groups.forEach((entry) => {
        const instanceData = initialisePicker(entry);
        if (instanceData) {
            instances.push(instanceData);
        }
    });

    instances.forEach(attachAnchorWatcher);

    return instances.map((item) => item.picker);
};

export const bootOrderDateRangePickers = () => {
    if (typeof document === 'undefined') {
        return;
    }

    const load = () => {
        initOrderDateRangePickers();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', load, { once: true });
    } else {
        load();
    }
};

bootOrderDateRangePickers();
