
<native:top-bar title="Entrar" />

<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full p-6 gap-5">

        <native:column class="w-full items-center gap-2 py-6">
            <native:icon name="clock" :size="48" class="text-theme-primary" />
            <native:text class="text-2xl font-bold">
                Ponto Fácil
            </native:text>
            <native:text class="text-theme-on-surface-variant">
                Entre na sua conta para continuar
            </native:text>
        </native:column>

        <native:column class="w-full gap-2">
            <native:text class="font-bold">
                E-mail
            </native:text>

            <native:outlined-text-input
                class="w-full"
                placeholder="Digite seu e-mail"
                keyboard="email"
                native:model.debounce.300ms="email"
            />
        </native:column>

        <native:column class="w-full gap-2">
            <native:text class="font-bold">
                Senha
            </native:text>

            <native:outlined-text-input
                class="w-full"
                placeholder="Digite sua senha"
                keyboard="password"
                secure
                revealable
                native:model.debounce.300ms="password"
                @submit="entrar"
            />
        </native:column>

        @if ($errorMessage)
            <native:column class="w-full p-3 rounded-lg bg-theme-surface-variant">
                <native:text>
                    Erro: {{ $errorMessage }}
                </native:text>
            </native:column>
        @endif

        @if ($successMessage)
            <native:column class="w-full p-3 rounded-lg bg-theme-surface-variant">
                <native:text>
                    {{ $successMessage }}
                </native:text>
            </native:column>
        @endif

        <native:button
            class="w-full"
            size="lg"
            @press="entrar"
        >
            Entrar
        </native:button>

        <native:text class="text-center text-theme-on-surface-variant">
            Ainda não possui uma conta?
        </native:text>

        <native:text class="text-center text-theme-primary">
            Cadastro disponível em breve.
        </native:text>

    </native:column>
</native:scroll-view>
