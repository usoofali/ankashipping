<x-layouts::app.sidebar title="WhatsApp Inbox">
    <flux:main>
        <x-crud.page-shell class="h-dvh overflow-hidden pb-12">
            @vite(['resources/js/whatsapp/app.js', 'resources/css/whatsapp.css'])
            
            <script>
                // Pass essential data from Laravel to Vue
                window.__whatsapp = {
                    user: {
                        id: {{ auth()->id() }},
                        name: @json(auth()->user()->name),
                    }
                };
            </script>
            
            <!-- Vue App Mount Point -->
            <div id="whatsapp-app" class="h-full"></div>
        </x-crud.page-shell>
    </flux:main>
</x-layouts::app.sidebar>
