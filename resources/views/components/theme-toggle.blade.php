<button type="button" @click="toggle()" :aria-label="dark ? 'Zum hellen Design wechseln' : 'Zum dunklen Design wechseln'"
        {{ $attributes->merge(['class' => 'grid size-10 place-items-center rounded-xl border border-line bg-panel text-ink2 hover:bg-panel2 transition']) }}>
    <span x-show="!dark"><x-icon name="sun" /></span>
    <span x-show="dark" x-cloak><x-icon name="moon" /></span>
</button>
