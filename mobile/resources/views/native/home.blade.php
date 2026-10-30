
<column
    ref="ponto-facil-home"
    fill
    class="safe-area bg-theme-background px-5 py-6"
>
    <column class="w-full gap-5">

        <!-- CABEÇALHO -->
        <row class="w-full items-center justify-between">
            <column class="gap-1">
                <text class="text-sm text-theme-on-surface-variant">
                    Bem-vindo ao
                </text>
                <text class="text-2xl font-bold text-theme-on-surface">
                    Ponto Fácil
                </text>
            </column>

            <box class="rounded-full bg-theme-primary p-3">
                <icon name="clock" class="text-theme-on-primary" size="24" />
            </box>
        </row>

        <!-- DATA -->
        <column class="gap-1">
            <text class="text-sm text-theme-on-surface-variant">
                Seu controle de jornada
            </text>
            <text class="text-lg font-semibold text-theme-on-surface">
                Acompanhe seu expediente
            </text>
        </column>

        <!-- RESUMO -->
        <column class="w-full rounded-2xl bg-theme-surface p-5 gap-3">
            <text class="text-sm text-theme-on-surface-variant">
                HORAS TRABALHADAS HOJE
            </text>

            <text class="text-3xl font-bold text-theme-on-surface">
                00h 00min
            </text>

            <divider />

            <row class="justify-between">
                <text class="text-sm text-theme-on-surface-variant">
                    Jornada prevista
                </text>
                <text class="text-sm font-semibold text-theme-on-surface">
                    08h 00min
                </text>
            </row>
        </column>

        <!-- MARCAÇÃO DE PONTO -->
        <column class="gap-3">
            <text class="text-lg font-bold text-theme-on-surface">
                Registrar ponto
            </text>

            <row class="w-full gap-3">
                <column
                    class="flex-1 rounded-xl bg-theme-primary p-4 items-center gap-2"
                    @press="registrarEntrada"
                >
                    <icon name="log-in" size="28" class="text-theme-on-primary" />
                    <text class="text-sm font-semibold text-theme-on-primary">
                        Entrada
                    </text>
                </column>

                <column
                    class="flex-1 rounded-xl bg-theme-surface p-4 items-center gap-2"
                    @press="iniciarIntervalo"
                >
                    <icon name="coffee" size="28" class="text-theme-on-surface" />
                    <text class="text-sm font-semibold text-theme-on-surface">
                        Início intervalo
                    </text>
                </column>
            </row>

            <row class="w-full gap-3">
                <column
                    class="flex-1 rounded-xl bg-theme-surface p-4 items-center gap-2"
                    @press="finalizarIntervalo"
                >
                    <icon name="play" size="28" class="text-theme-on-surface" />
                    <text class="text-sm font-semibold text-theme-on-surface">
                        Fim intervalo
                    </text>
                </column>

                <column
                    class="flex-1 rounded-xl bg-theme-surface p-4 items-center gap-2"
                    @press="registrarSaida"
                >
                    <icon name="log-out" size="28" class="text-theme-on-surface" />
                    <text class="text-sm font-semibold text-theme-on-surface">
                        Saída
                    </text>
                </column>
            </row>
        </column>

        <!-- BANCO DE HORAS -->
        <column class="rounded-2xl bg-theme-surface p-5 gap-2">
            <text class="text-sm text-theme-on-surface-variant">
                Banco de horas
            </text>

            <text class="text-2xl font-bold text-theme-on-surface">
                00h 00min
            </text>

            <text class="text-xs text-theme-on-surface-variant">
                Saldo acumulado
            </text>
        </column>

        <!-- RODAPÉ -->
        <text class="text-xs text-theme-on-surface-variant text-center">
            Ponto Fácil · Controle de jornada
        </text>

    </column>
</column>
