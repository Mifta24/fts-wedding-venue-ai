<span @class([
    'rounded-full px-2 py-0.5 text-xs',
    'bg-amber-100 text-amber-700' => $booking->status === 'pending',
    'bg-emerald-100 text-emerald-700' => $booking->status === 'confirmed',
    'bg-stone-100 text-stone-500' => $booking->status === 'cancelled',
])>{{ ucfirst($booking->status) }}</span>
