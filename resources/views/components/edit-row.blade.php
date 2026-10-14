@props(['action', 'key', 'colspan'])

{{--
    The edit form of one table row, sitting right under it (specs/024). It
    replaces the separate edit pages: the row opens in place, and saving comes
    back to the same page.

    Needs an Alpine `editing` (the id of the open row, 0 for none) on a parent
    around the table — one row open at a time. The page seeds it from
    old('editing_id'), so a row whose save just failed comes back open.

    The <form> lives inside the <td>: a form around a <tr> would be hoisted out
    of the table by the HTML parser.
--}}
<tr x-show="editing === {{ (int) $key }}" x-cloak class="bg-primary/5">
    <td colspan="{{ $colspan }}" class="px-4 py-3">
        <form method="POST" action="{{ $action }}" class="flex flex-wrap items-end gap-3">
            @csrf
            @method('PUT')
            <input type="hidden" name="editing_id" value="{{ (int) $key }}">

            {{ $slot }}

            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">{{ __('Save') }}</button>
            <button type="button" x-on:click="editing = 0" class="rounded-md border border-border px-4 py-2 text-sm text-ink-soft hover:bg-bg">إلغاء</button>
        </form>
    </td>
</tr>
