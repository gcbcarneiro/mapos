// Formulário de agendamento (views/agendamentos/formulario.php, fork Conser):
// liga os autocompletes de cliente e técnico (os mesmos da OS) e os atalhos
// de data ("Em 3 meses", "Em 6 meses", "Em 1 ano"), que trazem a data pronta
// do servidor em data-agendamento-atalho. A validação e o envio ficam com o
// módulo formulario/padrao, no <form>.

import { iniciarAutocompletes } from '../../lib/autocomplete.js';

export default function iniciar(elemento) {
    iniciarAutocompletes(elemento);

    const campoData = elemento.querySelector('[data-agendamento-data]');
    if (!campoData) {
        return;
    }

    elemento.querySelectorAll('[data-agendamento-atalho]').forEach((botao) => {
        botao.addEventListener('click', () => {
            campoData.value = botao.dataset.agendamentoAtalho;
            // Avisa a validação do formulário (lib/formulario.js) que o valor mudou.
            campoData.dispatchEvent(new Event('input', { bubbles: true }));
            campoData.dispatchEvent(new Event('change', { bubbles: true }));
            campoData.focus();
        });
    });
}
