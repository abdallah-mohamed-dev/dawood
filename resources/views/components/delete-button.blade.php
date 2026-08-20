@props(['action', 'label' => null])

<form method="POST" action="{{ $action }}" class="inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟');">
    @csrf
    @method('DELETE')
    <button type="submit" {{ $attributes->merge(['class' => 'text-danger hover:underline']) }}>{{ $label ?? __('Delete') }}</button>
</form>