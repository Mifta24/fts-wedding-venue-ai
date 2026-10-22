@if ($booking->canTransitionTo('confirmed'))
    <form method="POST" action="{{ route('admin.bookings.status', $booking) }}" class="inline">
        @csrf @method('PATCH')
        <input type="hidden" name="status" value="confirmed">
        <button type="submit" class="{{ $button ?? '' }} text-emerald-600 hover:underline">Confirm</button>
    </form>
@endif
@if ($booking->canTransitionTo('cancelled'))
    <form method="POST" action="{{ route('admin.bookings.status', $booking) }}" class="inline [&:not(:first-child)]:ml-3" onsubmit="return confirm('Cancel this booking?')">
        @csrf @method('PATCH')
        <input type="hidden" name="status" value="cancelled">
        <button type="submit" class="{{ $button ?? '' }} text-red-600 hover:underline">Cancel</button>
    </form>
@endif
