<div
    class="flex flex-col gap-2"
    wire:ignore
>
    <div class="flex justify-end">
        <select
            class="text-xs border-zinc-200 dark:border-zinc-700 rounded-md bg-transparent dark:text-zinc-300"
            wire:model.live="days"
        >
            <option value="7">Last 7 days</option>
            <option value="30">Last 30 days</option>
            <option value="90">Last 90 days</option>
            <option value="365">Last year</option>
        </select>
    </div>

    <div class="flex items-center justify-center h-48">
        <canvas id="chart"></canvas>
    </div>
</div>

@placeholder
    <div class="flex items-center justify-center h-48">
        <flux:skeleton.line class="h-full w-full rounded-lg" />
    </div>
@endplaceholder
