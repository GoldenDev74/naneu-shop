<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn-primaire']) }}>
    {{ $slot }}
</button>
