@props([
	'label',

	'value',
	'countColour' => null,

	'icon' => null,

	'button' => null,
	'buttonIcon' => 'funnel',
	'buttonTooltip' => null,

	'loading' => false,
	'mono' => true,
	'unit' => null,
	'of' => null,
	'prefix' => null,
])
<flux:card {{ $attributes->class(['w-full px-5 py-3 overflow-hidden flex flex-col relative'])}}>
	<div class="flex gap-3 items-center my-auto z-10">
		@if($icon)
			<flux:heading size="xl">
				<flux:icon :name="$icon"/>
			</flux:heading>
		@endif

		<div class="flex flex-col self-center w-full">
			<div class="flex justify-between items-center w-full">

				<flux:subheading class="whitespace-normal break-all line-clamp-1">
					{{ $label }}
				</flux:subheading>

				<flux:spacer/>

				@if($button === null)
				@elseif($button instanceof \Illuminate\View\ComponentSlot)
					{{ $button }}
				@else
					<flux:button
						:tooltip="$buttonTooltip"
						:icon="$buttonIcon"
						inset="right"
						size="sm"
						wire:click="{{ $button }}"
					/>
				@endif
			</div>

			@if($loading)
				<flux:heading size="lg">
					<div class="animate-pulse bg-gray-200 h-6 w-{{ \Illuminate\Support\Arr::random([8, 10, 12, 14, 16]) }} rounded"></div>
				</flux:heading>
			@elseif($value instanceof \Illuminate\View\ComponentSlot)
				{{ $value }}
			@else
				<flux:heading size="lg" class="h-6">
					@if($prefix !== null)
						<flux:text inline size="lg">{{ $prefix }}</flux:text>
					@endif
					<flux:text size="lg" inline :class="$mono ? 'font-mono' : ''" variant="strong" :color="$countColour">
						{{ $value }}
					</flux:text>
					@if($of !== null)
						<flux:text inline>of</flux:text>
						<span class="font-mono">{{ $of }}</span>
					@endif
					@if($unit !== null)
						<flux:text inline>{{ $unit }}</flux:text>
					@endif
				</flux:heading>
			@endif
		</div>
	</div>
</flux:card>
