// script.js
console.log("[DEBUG] script.js: Início da análise do script.");

// --- Constantes e Variáveis Globais ---
// Movidas para o topo do arquivo para garantir que estejam definidas antes de qualquer função.

const nomesMeses = {
  1: "Janeiro",
  2: "Fevereiro",
  3: "Março",
  4: "Abril",
  5: "Maio",
  6: "Junho",
  7: "Julho",
  8: "Agosto",
  9: "Setembro",
  10: "Outubro",
  11: "Novembro",
  12: "Dezembro",
};

const dataAtualGlobal = new Date();
let currentDisplayYear = dataAtualGlobal.getFullYear();
let currentDisplayMonth = dataAtualGlobal.getMonth() + 1; // getMonth() do JavaScript é 0-indexado

let currentDisplayYearAusencias = currentDisplayYear;
let currentDisplayMonthAusencias = currentDisplayMonth;
let currentDisplayYearFeriados = currentDisplayYear;
let currentDisplayMonthFeriados = currentDisplayMonth;
let currentDisplayYearEscalaSabados = currentDisplayYear;
let currentDisplayMonthEscalaSabados = currentDisplayMonth;
let currentDisplayYearAusenciaSetor = currentDisplayYear;
let currentDisplayMonthAusenciaSetor = currentDisplayMonth;

let todosOsColaboradores = [];
let activeToastTimeout = null;
let employeeHoursChartInstance = null;

// PREENCHA COM SUAS CLASSES TAILWIND REAIS
const tailwindInputClasses =
  "block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 bg-white text-gray-700 placeholder-gray-400";
const tailwindSelectClasses =
  "block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 bg-white text-gray-700";
const tailwindCheckboxClasses =
  "h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500";

// --- DEFINIÇÕES DE TODAS AS FUNÇÕES PRIMEIRO ---

function showToast(message, type = "info", duration = 3500) {
  const existingToast = document.getElementById("toast-notification");
  if (existingToast) {
    existingToast.remove();
    if (activeToastTimeout) clearTimeout(activeToastTimeout);
  }
  const toast = document.createElement("div");
  toast.id = "toast-notification";
  let bgColor = "bg-blue-500";
  if (type === "success") bgColor = "bg-green-500";
  if (type === "error") bgColor = "bg-red-500";
  if (type === "warning") bgColor = "bg-yellow-500 text-gray-800";

  toast.className = `fixed bottom-5 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-lg shadow-lg text-white text-sm font-medium z-[1060] transition-all duration-300 ease-out opacity-0 translate-y-10 ${bgColor}`;
  toast.textContent = message;
  document.body.appendChild(toast);

  requestAnimationFrame(() => {
    toast.classList.remove("opacity-0", "translate-y-10");
    toast.classList.add("opacity-100", "translate-y-0");
  });

  activeToastTimeout = setTimeout(() => {
    toast.classList.remove("opacity-100", "translate-y-0");
    toast.classList.add("opacity-0", "translate-y-10");
    toast.addEventListener("transitionend", () => toast.remove(), {
      once: true,
    });
  }, duration);
}

async function buscarEArmazenarColaboradores() {
  console.log("[DEBUG] buscarEArmazenarColaboradores chamado.");
  if (
    todosOsColaboradores.length > 0 &&
    todosOsColaboradores[0] &&
    todosOsColaboradores[0].hasOwnProperty("id")
  ) {
    console.log("[DEBUG] Usando colaboradores já armazenados.");
    return todosOsColaboradores;
  }
  try {
    console.log("[DEBUG] Buscando colaboradores do servidor...");
    const response = await fetch("obter_colaboradores.php");
    console.log(
      "[DEBUG] Resposta de obter_colaboradores.php status:",
      response.status
    );
    if (!response.ok) {
      let errorMsg = `Falha ao buscar colaboradores: HTTP ${response.status}`;
      try {
        const errData = await response.json();
        errorMsg = errData.message || errorMsg;
      } catch (e) {
        const errText = await response.text().catch(() => "");
        errorMsg = errText.substring(0, 150) || errorMsg;
      }
      throw new Error(errorMsg);
    }
    const data = await response.json();
    console.log("[DEBUG] Dados de colaboradores recebidos:", data);
    if (data.success && data.colaboradores) {
      todosOsColaboradores = data.colaboradores;
      return todosOsColaboradores;
    } else {
      showToast(
        data.message || "Falha ao carregar lista de colaboradores do backend.",
        "error"
      );
      todosOsColaboradores = [];
      return [];
    }
  } catch (error) {
    console.error("[DEBUG] Erro na requisição fetch de colaboradores:", error);
    showToast(
      `Erro crítico ao carregar colaboradores: ${error.message}`,
      "error"
    );
    todosOsColaboradores = [];
    return [];
  }
}

function popularSelectColaborador(selectElement, valorSelecionado = null) {
  selectElement.innerHTML =
    '<option value="" class="text-gray-500">Selecione...</option>';
  if (!Array.isArray(todosOsColaboradores)) {
    console.error(
      "[DEBUG] Erro: 'todosOsColaboradores' não é um array em popularSelectColaborador."
    );
    return;
  }
  todosOsColaboradores.forEach((colab) => {
    const option = document.createElement("option");
    option.value = colab.nome_completo;
    option.textContent = colab.nome_completo;
    if (valorSelecionado && colab.nome_completo === valorSelecionado)
      option.selected = true;
    selectElement.appendChild(option);
  });
}

function calcularDuracaoDecimal(horaInicioStr, horaFimStr) {
  if (!horaInicioStr || !horaFimStr) return 0;
  const [h1Str, m1Str] = horaInicioStr.split(":");
  const [h2Str, m2Str] = horaFimStr.split(":");

  const h1 = parseInt(h1Str, 10);
  const m1 = parseInt(m1Str, 10);
  const h2 = parseInt(h2Str, 10);
  const m2 = parseInt(m2Str, 10);

  if (isNaN(h1) || isNaN(m1) || isNaN(h2) || isNaN(m2)) return 0;

  let inicioEmMinutos = h1 * 60 + m1;
  let fimEmMinutos = h2 * 60 + m2;

  if (fimEmMinutos < inicioEmMinutos) {
    fimEmMinutos += 24 * 60;
  }

  const duracaoEmMinutos = fimEmMinutos - inicioEmMinutos;
  return duracaoEmMinutos > 0 ? duracaoEmMinutos / 60.0 : 0;
}

async function popularTabelaTurnos(turnos) {
  const corpoTabela = document.querySelector("#shifts-table-main tbody");
  if (!corpoTabela) {
    console.error(
      "[DEBUG] Tabela de turnos (tbody) não encontrada em popularTabelaTurnos."
    );
    return;
  }
  corpoTabela.innerHTML = "";
  const chkAll = document.getElementById("select-all-shifts");
  if (chkAll) chkAll.checked = false;

  if (
    todosOsColaboradores.length === 0 ||
    !todosOsColaboradores[0] ||
    !todosOsColaboradores[0].hasOwnProperty("id")
  ) {
    await buscarEArmazenarColaboradores();
  }

  if (!turnos || turnos.length === 0) {
    const r = corpoTabela.insertRow();
    r.className = "bg-white";
    const c = r.insertCell();
    c.colSpan = 5;
    c.className = "p-2 text-center text-gray-500 text-sm";
    c.textContent = "Nenhum turno programado para este período.";
    return;
  }

  turnos.forEach((turno) => {
    const nLinha = corpoTabela.insertRow();
    nLinha.className = "bg-white hover:bg-gray-50";
    nLinha.setAttribute("data-turno-id", turno.id);

    const cellCheckbox = nLinha.insertCell();
    cellCheckbox.className = "p-2 text-center";
    const inputCheckbox = document.createElement("input");
    inputCheckbox.type = "checkbox";
    inputCheckbox.className = `shift-select-checkbox ${tailwindCheckboxClasses}`;
    inputCheckbox.value = turno.id;
    cellCheckbox.appendChild(inputCheckbox);

    const cellData = nLinha.insertCell();
    cellData.className = "p-1";
    const inputData = document.createElement("input");
    inputData.type = "text";
    inputData.className = `shift-date ${tailwindInputClasses}`;
    inputData.value = turno.data_formatada || turno.data;
    inputData.placeholder = "dd/Mês";
    cellData.appendChild(inputData);

    const cellInicio = nLinha.insertCell();
    cellInicio.className = "p-1";
    const inputInicio = document.createElement("input");
    inputInicio.type = "time";
    inputInicio.className = `shift-time-inicio ${tailwindInputClasses}`;
    inputInicio.value = turno.hora_inicio
      ? turno.hora_inicio.substring(0, 5)
      : "";
    cellInicio.appendChild(inputInicio);

    const cellFim = nLinha.insertCell();
    cellFim.className = "p-1";
    const inputFim = document.createElement("input");
    inputFim.type = "time";
    inputFim.className = `shift-time-fim ${tailwindInputClasses}`;
    inputFim.value = turno.hora_fim ? turno.hora_fim.substring(0, 5) : "";
    cellFim.appendChild(inputFim);

    const cellColab = nLinha.insertCell();
    cellColab.className = "p-1";
    const selColab = document.createElement("select");
    selColab.className = `shift-employee shift-employee-select ${tailwindSelectClasses}`;
    popularSelectColaborador(selColab, turno.colaborador);
    cellColab.appendChild(selColab);
  });
}

function coletarDadosDaTabelaDeTurnos() {
  const linhas = document.querySelectorAll("#shifts-table-main tbody tr");
  const dados = [];
  const displayElement = document.getElementById("current-month-year-display");
  const anoTabela =
    displayElement && displayElement.dataset.year
      ? parseInt(displayElement.dataset.year, 10)
      : currentDisplayYear;

  let erroValidacaoGeralTurnos = false;

  linhas.forEach((linha, index) => {
    if (linha.cells.length === 1 && linha.cells[0].colSpan > 1) return;

    const dataIn = linha.querySelector(".shift-date");
    const horaInicioIn = linha.querySelector(".shift-time-inicio");
    const horaFimIn = linha.querySelector(".shift-time-fim");
    const colabSel = linha.querySelector(".shift-employee-select");
    const idOrig = linha.getAttribute("data-turno-id");

    const dataVal = dataIn ? dataIn.value.trim() : "";
    const inicioVal = horaInicioIn ? horaInicioIn.value.trim() : "";
    const fimVal = horaFimIn ? horaFimIn.value.trim() : "";
    const colabVal = colabSel ? colabSel.value.trim() : "";

    if (dataVal && inicioVal && fimVal && colabVal) {
      const inicioTotalMin =
        parseInt(inicioVal.split(":")[0], 10) * 60 +
        parseInt(inicioVal.split(":")[1], 10);
      const fimTotalMin =
        parseInt(fimVal.split(":")[0], 10) * 60 +
        parseInt(fimVal.split(":")[1], 10);

      if (
        fimTotalMin <= inicioTotalMin &&
        !(
          parseInt(fimVal.split(":")[0], 10) < 6 &&
          parseInt(inicioVal.split(":")[0], 10) > 18
        )
      ) {
        showToast(
          `Atenção (linha ${
            index + 1
          }): Turno para ${colabVal} em ${dataVal} tem Hora Fim (${fimVal}) não posterior à Hora Início (${inicioVal}). Este turno não será salvo.`,
          "warning",
          7000
        );
        erroValidacaoGeralTurnos = true;
        return;
      }
      dados.push({
        id: idOrig && !idOrig.startsWith("new-") ? idOrig : null,
        data: dataVal,
        hora_inicio: inicioVal,
        hora_fim: fimVal,
        colaborador: colabVal,
        ano: anoTabela.toString(),
      });
    } else if (
      !(dataVal === "" && inicioVal === "" && fimVal === "" && colabVal === "")
    ) {
      showToast(
        `Linha de turno ${
          index + 1
        } incompleta não será salva. Preencha todos os campos: Dia, Início, Fim e Colaborador.`,
        "warning",
        5000
      );
      erroValidacaoGeralTurnos = true;
    }
  });

  if (erroValidacaoGeralTurnos && dados.length === 0) return [];
  if (erroValidacaoGeralTurnos && dados.length > 0) return null;
  return dados;
}

async function salvarDadosTurnosNoServidor(dadosTurnos, csrfToken) {
  const btnSalvar = document.getElementById("save-shifts-button");
  const originalButtonHTML = btnSalvar ? btnSalvar.innerHTML : "";
  if (btnSalvar) {
    btnSalvar.disabled = true;
    btnSalvar.innerHTML = `<i data-lucide="loader-circle" class="lucide-spin w-4 h-4 mr-1.5"></i> Salvando...`;
    if (typeof lucide !== "undefined") lucide.createIcons();
  }

  const payload = {
    acao: "salvar_turnos",
    turnos: dadosTurnos,
    csrf_token: csrfToken,
  };

  try {
    const response = await fetch("salvar_turnos.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });

    let data;
    if (!response.ok) {
      let errorMsg = `Erro do servidor: HTTP ${response.status}`;
      try {
        data = await response.json();
        errorMsg = data.message || errorMsg;
      } catch (e) {
        const errText = await response.text().catch(() => "");
        errorMsg = errText.substring(0, 150) || errorMsg;
      }
      throw new Error(errorMsg);
    }
    data = await response.json();

    if (data.success) {
      showToast(data.message || "Turnos salvos com sucesso!", "success");
      if (data.csrf_token) {
        const csrfInput = document.getElementById("csrf-token-shifts");
        if (csrfInput) csrfInput.value = data.csrf_token;
      }
      await carregarTurnosDoServidor(
        currentDisplayYear,
        currentDisplayMonth,
        true
      );
    } else {
      showToast(
        "Erro ao salvar: " + (data.message || "Erro desconhecido do servidor."),
        "error"
      );
    }
  } catch (error) {
    console.error("[DEBUG] Erro crítico ao salvar turnos:", error);
    showToast(`Erro crítico ao salvar: ${error.message}`, "error");
  } finally {
    if (btnSalvar) {
      btnSalvar.disabled = false;
      btnSalvar.innerHTML = originalButtonHTML;
      if (typeof lucide !== "undefined") lucide.createIcons();
    }
  }
}

async function excluirTurnosNoServidor(ids, csrfToken) {
  if (!ids || ids.length === 0) {
    showToast("Nenhum turno selecionado.", "info");
    return;
  }
  if (
    !confirm(
      `Tem certeza que deseja excluir ${ids.length} turno(s)? Esta ação não pode ser desfeita.`
    )
  )
    return;

  try {
    const response = await fetch("salvar_turnos.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        acao: "excluir_turnos",
        ids_turnos: ids,
        csrf_token: csrfToken,
      }),
    });

    let data;
    if (!response.ok) {
      let errorMsg = `Erro do servidor: HTTP ${response.status}`;
      try {
        data = await response.json();
        errorMsg = data.message || errorMsg;
      } catch (e) {
        const errText = await response.text().catch(() => "");
        errorMsg = errText.substring(0, 150) || errorMsg;
      }
      throw new Error(errorMsg);
    }
    data = await response.json();

    if (data.success) {
      showToast(data.message || "Turno(s) excluído(s) com sucesso!", "success");
      if (data.csrf_token) {
        const csrfInput = document.getElementById("csrf-token-shifts");
        if (csrfInput) csrfInput.value = data.csrf_token;
      }
      carregarTurnosDoServidor(currentDisplayYear, currentDisplayMonth, true);
    } else {
      showToast(
        "Erro ao excluir: " + (data.message || "Erro do servidor."),
        "error"
      );
    }
  } catch (error) {
    console.error("[DEBUG] Erro crítico ao excluir turnos:", error);
    showToast(`Erro crítico ao excluir: ${error.message}.`, "error");
  }
}

function atualizarTabelaResumoColaboradores(turnos) {
  const tbody = document.querySelector("#employee-summary-table tbody");
  if (!tbody) return;
  tbody.innerHTML = "";
  if (!turnos || turnos.length === 0) {
    const r = tbody.insertRow();
    r.className = "bg-white";
    const c = r.insertCell();
    c.colSpan = 2;
    c.className = "p-2 text-center text-gray-500 text-sm";
    c.textContent = "Sem dados para resumo.";
    return;
  }
  const resumo = {};
  turnos.forEach((t) => {
    if (!t.colaborador || !t.hora_inicio || !t.hora_fim) return;
    if (!resumo[t.colaborador]) resumo[t.colaborador] = 0;
    resumo[t.colaborador] += calcularDuracaoDecimal(t.hora_inicio, t.hora_fim);
  });

  const colaboradoresOrdenados = Object.keys(resumo).sort();

  for (const colab of colaboradoresOrdenados) {
    if (resumo[colab] > 0.005) {
      const tot = resumo[colab].toFixed(2);
      const r = tbody.insertRow();
      r.className = "bg-white hover:bg-gray-50";
      const cellColab = r.insertCell();
      cellColab.className = "p-2 text-sm text-gray-700";
      cellColab.textContent = colab;
      const cellHoras = r.insertCell();
      cellHoras.className = "p-2 text-sm text-gray-700 text-right";
      cellHoras.textContent = tot.replace(".", ",") + "h";
    }
  }
}

function atualizarGraficoResumoHoras(turnos) {
  const ctx = document.getElementById("employee-hours-chart");
  if (!ctx) return;
  const resumo = {};
  if (turnos && turnos.length > 0) {
    turnos.forEach((t) => {
      if (!t.colaborador || !t.hora_inicio || !t.hora_fim) return;
      if (!resumo[t.colaborador]) resumo[t.colaborador] = 0;
      resumo[t.colaborador] += calcularDuracaoDecimal(
        t.hora_inicio,
        t.hora_fim
      );
    });
  }

  const labels = Object.keys(resumo)
    .filter((colab) => resumo[colab] > 0.005)
    .sort();
  const dataPoints = labels.map((l) => parseFloat(resumo[l].toFixed(2)));

  if (labels.length === 0) {
    if (employeeHoursChartInstance) {
      employeeHoursChartInstance.destroy();
      employeeHoursChartInstance = null;
    }
    const context = ctx.getContext("2d");
    context.clearRect(0, 0, ctx.width, ctx.height);
    context.textAlign = "center";
    context.textBaseline = "middle";
    context.font = "14px Poppins, sans-serif";
    context.fillStyle = "#6b7280";
    context.fillText(
      "Sem dados para exibir no gráfico.",
      ctx.width / 2,
      ctx.height / 2
    );
    return;
  }

  const tailwindColors = [
    "rgba(59, 130, 246, 0.7)",
    "rgba(16, 185, 129, 0.7)",
    "rgba(234, 179, 8, 0.7)",
    "rgba(239, 68, 68, 0.7)",
    "rgba(139, 92, 246, 0.7)",
    "rgba(236, 72, 153, 0.7)",
    "rgba(249, 115, 22, 0.7)",
  ];
  const borderColors = tailwindColors.map((color) => color.replace("0.7", "1"));

  if (employeeHoursChartInstance) {
    employeeHoursChartInstance.data.labels = labels;
    employeeHoursChartInstance.data.datasets[0].data = dataPoints;
    employeeHoursChartInstance.data.datasets[0].backgroundColor =
      tailwindColors.slice(0, dataPoints.length);
    employeeHoursChartInstance.data.datasets[0].borderColor =
      borderColors.slice(0, dataPoints.length);
    employeeHoursChartInstance.update();
  } else {
    employeeHoursChartInstance = new Chart(ctx, {
      type: "bar",
      data: {
        labels,
        datasets: [
          {
            label: "Total de Horas",
            data: dataPoints,
            backgroundColor: tailwindColors.slice(0, dataPoints.length),
            borderColor: borderColors.slice(0, dataPoints.length),
            borderWidth: 1,
            borderRadius: 4,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: "Horas",
              font: { family: "Poppins" },
            },
            ticks: { font: { family: "Poppins" } },
          },
          x: {
            title: { display: false },
            ticks: { font: { family: "Poppins" } },
          },
        },
        plugins: {
          legend: {
            display: dataPoints.length > 1,
            position: "bottom",
            labels: { font: { family: "Poppins" } },
          },
          title: { display: false },
          tooltip: {
            bodyFont: { family: "Poppins" },
            titleFont: { family: "Poppins" },
            callbacks: {
              label: (c) =>
                (c.dataset.label || "") +
                ": " +
                (c.parsed.y !== null
                  ? c.parsed.y.toFixed(2).replace(".", ",") + "h"
                  : ""),
            },
          },
        },
      },
    });
  }
}

function updateCurrentMonthYearDisplay() {
  console.log("[DEBUG] updateCurrentMonthYearDisplay: Iniciando.");
  const displayElement = document.getElementById("current-month-year-display");
  const summaryPeriodElement = document.getElementById(
    "employee-summary-period"
  );
  if (displayElement) {
    const monthName =
      nomesMeses[currentDisplayMonth] || `Mês ${currentDisplayMonth}`;
    displayElement.innerHTML = `<i data-lucide="list-todo" class="w-5 h-5 mr-2 text-blue-600"></i> Turnos - ${monthName} ${currentDisplayYear}`;
    displayElement.dataset.year = currentDisplayYear;
    displayElement.dataset.month = currentDisplayMonth;
    if (typeof lucide !== "undefined") lucide.createIcons();
  } else {
    console.warn(
      "[DEBUG] updateCurrentMonthYearDisplay: Elemento 'current-month-year-display' não encontrado."
    );
  }
  if (summaryPeriodElement) {
    summaryPeriodElement.textContent = nomesMeses[currentDisplayMonth] || "";
  } else {
    console.warn(
      "[DEBUG] updateCurrentMonthYearDisplay: Elemento 'employee-summary-period' não encontrado."
    );
  }
  console.log("[DEBUG] updateCurrentMonthYearDisplay: Finalizado.");
}

async function carregarTurnosDoServidor(
  ano,
  mes,
  atualizarResumosGlobais = true
) {
  const shiftsTableBody = document.querySelector("#shifts-table-main tbody");
  const csrfInputOriginal = document.getElementById("csrf-token-shifts");

  if (shiftsTableBody) {
    shiftsTableBody.innerHTML = `<tr><td colspan="5" class="p-2 text-center text-gray-500 text-sm">Carregando turnos... <i data-lucide="loader-circle" class="lucide-spin inline-block w-4 h-4"></i></td></tr>`;
    if (typeof lucide !== "undefined") lucide.createIcons();
  } else {
    console.error(
      "[DEBUG] Elemento tbody da tabela de turnos não encontrado em carregarTurnosDoServidor."
    );
    return;
  }

  try {
    const response = await fetch(`salvar_turnos.php?ano=${ano}&mes=${mes}`);
    let data;
    if (!response.ok) {
      let errorMsg = `Erro HTTP ${response.status}`;
      try {
        data = await response.json();
        errorMsg = data.message || errorMsg;
      } catch (e) {
        const errText = await response.text().catch(() => "");
        errorMsg = errText.substring(0, 150) || errorMsg;
      }
      throw new Error(errorMsg);
    }
    data = await response.json();

    if (data.success) {
      if (data.csrf_token && csrfInputOriginal)
        csrfInputOriginal.value = data.csrf_token;
      await popularTabelaTurnos(data.data || []);
      if (atualizarResumosGlobais) {
        atualizarTabelaResumoColaboradores(data.data || []);
        atualizarGraficoResumoHoras(data.data || []);
      }
    } else {
      showToast(
        "Aviso: " + (data.message || "Não foi possível carregar turnos."),
        "warning"
      );
      await popularTabelaTurnos([]);
      if (atualizarResumosGlobais) {
        atualizarTabelaResumoColaboradores([]);
        atualizarGraficoResumoHoras([]);
      }
    }
  } catch (error) {
    console.error(`[DEBUG] Erro ao carregar turnos para ${mes}/${ano}:`, error);
    showToast(
      `Erro ao carregar turnos: ${error.message}. Verifique o console.`,
      "error"
    );
    await popularTabelaTurnos([]);
    if (atualizarResumosGlobais) {
      atualizarTabelaResumoColaboradores([]);
      atualizarGraficoResumoHoras([]);
    }
  }
}

async function carregarObservacaoGeral() {
  const textarea = document.getElementById("observacoes-gerais-textarea");
  const csrfTokenObsGeralInput = document.getElementById(
    "csrf-token-obs-geral"
  );
  if (!textarea || !csrfTokenObsGeralInput) return;

  try {
    const response = await fetch("gerenciar_observacao_geral.php");
    let data;
    if (!response.ok) {
      let errorMsg = `Erro HTTP ${response.status}`;
      try {
        data = await response.json();
        errorMsg = data.message || errorMsg;
      } catch (e) {
        const errText = await response.text().catch(() => "");
        errorMsg = errText.substring(0, 150) || errorMsg;
      }
      throw new Error(errorMsg);
    }
    data = await response.json();

    if (data.success) {
      textarea.value = data.observacao || "";
      if (data.csrf_token) csrfTokenObsGeralInput.value = data.csrf_token;
    } else {
      showToast(data.message || "Erro ao carregar observação.", "error");
    }
  } catch (error) {
    console.error("[DEBUG] Erro de conexão ao carregar observação:", error);
    showToast(
      "Erro de conexão ao carregar observação: " + error.message,
      "error"
    );
  }
}

async function salvarObservacaoGeral() {
  const textarea = document.getElementById("observacoes-gerais-textarea");
  const csrfTokenInput = document.getElementById("csrf-token-obs-geral");
  const saveButton = document.getElementById("salvar-observacoes-gerais-btn");
  if (!textarea || !csrfTokenInput || !saveButton) return;

  const originalButtonHtml = saveButton.innerHTML;
  saveButton.disabled = true;
  saveButton.innerHTML = `<i data-lucide="loader-circle" class="lucide-spin w-4 h-4 mr-1.5"></i> Salvando...`;
  if (typeof lucide !== "undefined") lucide.createIcons();

  const payload = {
    observacao: textarea.value,
    csrf_token: csrfTokenInput.value,
  };
  try {
    const response = await fetch("gerenciar_observacao_geral.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    let data;
    if (!response.ok) {
      let errorMsg = `Erro do servidor: HTTP ${response.status}`;
      try {
        data = await response.json();
        errorMsg = data.message || errorMsg;
      } catch (e) {
        const errText = await response.text().catch(() => "");
        errorMsg = errText.substring(0, 150) || errorMsg;
      }
      throw new Error(errorMsg);
    }
    data = await response.json();

    if (data.success) {
      showToast(data.message || "Observação salva!", "success");
      if (data.csrf_token) csrfTokenInput.value = data.csrf_token;
    } else {
      showToast(data.message || "Erro ao salvar observação.", "error");
    }
  } catch (error) {
    console.error("[DEBUG] Erro de conexão ao salvar observação:", error);
    showToast(
      "Erro de conexão ao salvar observação: " + error.message,
      "error"
    );
  } finally {
    saveButton.disabled = false;
    saveButton.innerHTML = originalButtonHtml;
    if (typeof lucide !== "undefined") lucide.createIcons();
  }
}

function updateFeriadosDisplay(ano, mes) {
  const displayElement = document.getElementById("feriados-mes-ano-display");
  if (displayElement) {
    const monthName = nomesMeses[mes] || `Mês ${mes}`;
    displayElement.innerHTML = `<i data-lucide="calendar-heart" class="w-4 h-4 mr-2 text-blue-600"></i> Feriados - ${monthName} ${ano}`;
    if (typeof lucide !== "undefined") lucide.createIcons();
  }
}

async function carregarFeriados(ano, mes) {
  const tbody = document.querySelector("#feriados-table tbody");
  if (!tbody) return;
  tbody.innerHTML = `<tr><td colspan="2" class="p-2 text-center text-gray-500 text-sm">Carregando... <i data-lucide="loader-circle" class="lucide-spin inline-block w-4 h-4"></i></td></tr>`;
  if (typeof lucide !== "undefined") lucide.createIcons();
  try {
    const response = await fetch(`carregar_feriados.php?ano=${ano}&mes=${mes}`);
    let data;
    if (!response.ok) {
      let errorMsg = `Erro HTTP ${response.status}`;
      try {
        data = await response.json();
        errorMsg = data.message || errorMsg;
      } catch (e) {
        const errText = await response.text().catch(() => "");
        errorMsg = errText.substring(0, 150) || errorMsg;
      }
      throw new Error(errorMsg);
    }
    data = await response.json();

    tbody.innerHTML = "";
    if (data.success && data.feriados) {
      if (data.feriados.length === 0) {
        const r = tbody.insertRow();
        r.className = "bg-white";
        const c = r.insertCell();
        c.colSpan = 2;
        c.className = "p-2 text-center text-gray-500 text-sm";
        c.textContent = "Nenhum feriado encontrado para este mês.";
      } else {
        data.feriados.forEach((feriado) => {
          const r = tbody.insertRow();
          r.className = "bg-white hover:bg-gray-50";
          const cellData = r.insertCell();
          cellData.className = "p-2 text-sm text-gray-700";
          cellData.textContent = feriado.data;
          const cellObs = r.insertCell();
          cellObs.className = "p-2 text-sm text-gray-700";
          cellObs.textContent = feriado.observacao;
        });
      }
    } else {
      showToast(data.message || "Erro ao carregar feriados.", "warning");
      const r = tbody.insertRow();
      r.className = "bg-white";
      const c = r.insertCell();
      c.colSpan = 2;
      c.className = "p-2 text-center text-red-500 text-sm";
      c.textContent = data.message || "Erro ao carregar feriados.";
    }
  } catch (error) {
    console.error("[DEBUG] Erro ao buscar feriados:", error);
    tbody.innerHTML = `<tr><td colspan="2" class="p-2 text-center text-red-500 text-sm">Erro de conexão ao carregar feriados.</td></tr>`;
    showToast(
      "Erro de conexão ao carregar feriados: " + error.message,
      "error"
    );
  }
}

function updateCurrentMonthYearDisplayAusencias() {
  const displayElement = document.getElementById(
    "current-month-year-ausencias-display"
  );
  if (displayElement) {
    const monthName =
      nomesMeses[currentDisplayMonthAusencias] ||
      `Mês ${currentDisplayMonthAusencias}`;
    displayElement.innerHTML = `<i data-lucide="user-x" class="w-5 h-5 mr-2 text-blue-600"></i> Ausências - ${monthName} ${currentDisplayYearAusencias}`;
    displayElement.dataset.year = currentDisplayYearAusencias;
    displayElement.dataset.month = currentDisplayMonthAusencias;
    if (typeof lucide !== "undefined") lucide.createIcons();
  }
}

async function carregarAusenciasDoServidor(ano, mes) {
  console.log(
    `[DEBUG] carregarAusenciasDoServidor chamado com ano: ${ano}, mes: ${mes}`
  );
  const tableBody = document.querySelector("#ausencias-table-main tbody");
  const csrfTokenInput = document.getElementById("csrf-token-ausencias");

  if (!tableBody) {
    console.error(
      "[DEBUG] Tabela de ausências (#ausencias-table-main tbody) NÃO ENCONTRADA."
    );
    showToast(
      "Erro crítico: Estrutura da página de ausências incompleta (tbody).",
      "error"
    );
    return;
  }
  if (!csrfTokenInput && document.getElementById("save-ausencias-button")) {
    console.warn(
      "[DEBUG] Campo CSRF para ausências (csrf-token-ausencias) NÃO ENCONTRADO, mas botões de ação podem existir."
    );
  }

  tableBody.innerHTML = `<tr><td colspan="5" class="p-2 text-center text-gray-500 text-sm">Carregando ausências (${mes}/${ano})... <i data-lucide="loader-circle" class="lucide-spin inline-block w-4 h-4"></i></td></tr>`;
  if (typeof lucide !== "undefined") lucide.createIcons();

  const url = `gerenciar_ausencias.php?ano=${ano}&mes=${mes}`;
  console.log(`[DEBUG] Preparando para fazer fetch para ${url}`);
  try {
    const response = await fetch(url);
    console.log(
      `[DEBUG] Fetch para ${url} concluído. Status:`,
      response.status
    );

    let data;
    if (!response.ok) {
      let errorMsg = `Erro ao buscar ausências: HTTP ${response.status}`;
      try {
        const errData = await response.json();
        errorMsg = errData.message || errorMsg;
        console.error(
          "[DEBUG] Erro JSON da resposta não OK de gerenciar_ausencias.php:",
          errData
        );
      } catch (e) {
        const errText = await response
          .text()
          .catch(() => `Resposta não textual ou vazia.`);
        errorMsg = errText.substring(0, 150) || errorMsg;
        console.error(
          "[DEBUG] Erro TEXTO da resposta não OK de gerenciar_ausencias.php:",
          errText.substring(0, 300)
        );
      }
      throw new Error(errorMsg);
    }

    data = await response.json();
    console.log(
      "[DEBUG] Resposta JSON de gerenciar_ausencias.php:",
      JSON.stringify(data, null, 2)
    );

    if (data.success) {
      if (csrfTokenInput && data.csrf_token) {
        csrfTokenInput.value = data.csrf_token;
        console.log("[DEBUG] CSRF token para ausências atualizado.");
      }
      if (todosOsColaboradores.length === 0) {
        await buscarEArmazenarColaboradores();
      }
      popularTabelaAusencias(data.data || []);
    } else {
      showToast(
        "Aviso ao carregar ausências: " +
          (data.message || "Não foi possível carregar os dados."),
        "warning"
      );
      popularTabelaAusencias([]);
    }
  } catch (error) {
    console.error(
      "[DEBUG] Erro CATCH no fetch de carregarAusenciasDoServidor:",
      error
    );
    showToast(
      `Erro crítico ao carregar ausências: ${error.message}. Consulte o console.`,
      "error"
    );
    popularTabelaAusencias([]);
  }
}

function popularTabelaAusencias(ausencias) {
  console.log("[DEBUG] popularTabelaAusencias chamada com dados:", ausencias);
  const corpoTabela = document.querySelector("#ausencias-table-main tbody");
  if (!corpoTabela) {
    console.error(
      "[DEBUG] Tabela de ausências (tbody) não encontrada em popularTabelaAusencias."
    );
    return;
  }
  corpoTabela.innerHTML = "";
  const chkAll = document.getElementById("select-all-ausencias");
  if (chkAll) chkAll.checked = false;

  if (!ausencias || ausencias.length === 0) {
    const r = corpoTabela.insertRow();
    r.className = "bg-white";
    const c = r.insertCell();
    c.colSpan = 5;
    c.className = "p-2 text-center text-gray-500 text-sm";
    c.textContent = "Nenhuma ausência registrada para este período.";
    console.log("[DEBUG] Nenhuma ausência para popular na tabela.");
    return;
  }
  ausencias.forEach((item) => {
    const nLinha = corpoTabela.insertRow();
    nLinha.className = "bg-white hover:bg-gray-50";
    nLinha.setAttribute("data-ausencia-id", item.id);

    const cellCheckbox = nLinha.insertCell();
    cellCheckbox.className = "p-2 text-center";
    const inputCheckbox = document.createElement("input");
    inputCheckbox.type = "checkbox";
    inputCheckbox.className = `ausencia-select-checkbox ${tailwindCheckboxClasses}`;
    inputCheckbox.value = item.id;
    cellCheckbox.appendChild(inputCheckbox);

    const cellDataInicio = nLinha.insertCell();
    cellDataInicio.className = "p-1";
    const inputDataInicio = document.createElement("input");
    inputDataInicio.type = "date";
    inputDataInicio.className = `ausencia-data-inicio ${tailwindInputClasses}`;
    inputDataInicio.value = item.data_inicio || "";
    cellDataInicio.appendChild(inputDataInicio);

    const cellDataFim = nLinha.insertCell();
    cellDataFim.className = "p-1";
    const inputDataFim = document.createElement("input");
    inputDataFim.type = "date";
    inputDataFim.className = `ausencia-data-fim ${tailwindInputClasses}`;
    inputDataFim.value = item.data_fim || "";
    cellDataFim.appendChild(inputDataFim);

    const cellColaborador = nLinha.insertCell();
    cellColaborador.className = "p-1";
    const selectColaborador = document.createElement("select");
    selectColaborador.className = `ausencia-colaborador ${tailwindSelectClasses}`;
    popularSelectColaborador(selectColaborador, item.colaborador_nome);
    cellColaborador.appendChild(selectColaborador);

    const cellObs = nLinha.insertCell();
    cellObs.className = "p-1";
    const inputObs = document.createElement("input");
    inputObs.type = "text";
    inputObs.className = `ausencia-observacoes ${tailwindInputClasses}`;
    inputObs.value = item.observacoes || "";
    inputObs.placeholder = "Motivo/Observações da ausência";
    cellObs.appendChild(inputObs);
  });
  console.log(`[DEBUG] ${ausencias.length} ausência(s) populada(s) na tabela.`);
}

function coletarDadosDaTabelaDeAusencias() {
  const linhas = document.querySelectorAll("#ausencias-table-main tbody tr");
  const dados = [];
  let erroValidacaoGeral = false;
  linhas.forEach((linha, index) => {
    if (linha.cells.length === 1 && linha.cells[0].colSpan > 1) return;
    const idOrig = linha.getAttribute("data-ausencia-id");
    const dataInicioIn = linha.querySelector(".ausencia-data-inicio");
    const dataFimIn = linha.querySelector(".ausencia-data-fim");
    const colaboradorSelect = linha.querySelector(".ausencia-colaborador");
    const observacoesIn = linha.querySelector(".ausencia-observacoes");

    const inicioVal = dataInicioIn ? dataInicioIn.value.trim() : "";
    const fimVal = dataFimIn ? dataFimIn.value.trim() : "";
    const colaboradorVal = colaboradorSelect ? colaboradorSelect.value : "";
    const obsVal = observacoesIn ? observacoesIn.value.trim() : "";

    if ((colaboradorVal || obsVal) && (!inicioVal || !fimVal)) {
      showToast(
        `Atenção (linha ${
          index + 1
        }): Datas de Início e Fim são obrigatórias se um Colaborador ou Observação for fornecido. Não será salvo.`,
        "warning",
        7000
      );
      erroValidacaoGeral = true;
      return;
    }

    if (inicioVal && fimVal) {
      if (new Date(fimVal) < new Date(inicioVal)) {
        showToast(
          `Atenção (linha ${index + 1}): Data Fim (${new Date(
            fimVal
          ).toLocaleDateString()}) não pode ser anterior à Data Início (${new Date(
            inicioVal
          ).toLocaleDateString()}) para '${
            colaboradorVal || obsVal || "ausência"
          }'. Não será salvo.`,
          "warning",
          7000
        );
        erroValidacaoGeral = true;
        return;
      }
      dados.push({
        id: idOrig && !idOrig.startsWith("new-") ? idOrig : null,
        data_inicio: inicioVal,
        data_fim: fimVal,
        colaborador_nome: colaboradorVal,
        observacoes: obsVal,
      });
    } else if (colaboradorVal || obsVal) {
      if (!erroValidacaoGeral) {
        showToast(
          `Linha de ausência ${index + 1} incompleta (faltam datas) para '${
            colaboradorVal || obsVal
          }'. Não será salva.`,
          "warning",
          5000
        );
      }
      erroValidacaoGeral = true;
    }
  });

  if (erroValidacaoGeral && dados.length === 0) return [];
  if (erroValidacaoGeral && dados.length > 0) return null;
  return dados;
}

async function salvarDadosAusenciasNoServidor(dadosAusencias, csrfToken) {
  const btnSalvar = document.getElementById("save-ausencias-button");
  const originalButtonHtml = btnSalvar ? btnSalvar.innerHTML : "";
  if (btnSalvar) {
    btnSalvar.disabled = true;
    btnSalvar.innerHTML = `<i data-lucide="loader-circle" class="lucide-spin w-4 h-4 mr-1.5"></i> Salvando...`;
    if (typeof lucide !== "undefined") lucide.createIcons();
  }
  const payload = {
    acao: "salvar_ausencias",
    ausencias: dadosAusencias,
    csrf_token: csrfToken,
  };
  try {
    const response = await fetch("gerenciar_ausencias.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    let data;
    if (!response.ok) {
      let errorMsg = `Erro do servidor: HTTP ${response.status}`;
      try {
        data = await response.json();
        errorMsg = data.message || errorMsg;
      } catch (e) {
        const errText = await response.text().catch(() => "");
        errorMsg = errText.substring(0, 150) || errorMsg;
      }
      throw new Error(errorMsg);
    }
    data = await response.json();

    if (data.success) {
      showToast(data.message || "Ausências salvas com sucesso!", "success");
      if (data.csrf_token) {
        const csrfInput = document.getElementById("csrf-token-ausencias");
        if (csrfInput) csrfInput.value = data.csrf_token;
      }
      carregarAusenciasDoServidor(
        currentDisplayYearAusencias,
        currentDisplayMonthAusencias
      );
    } else {
      showToast(
        "Erro ao salvar ausências: " + (data.message || "Erro desconhecido."),
        "error"
      );
    }
  } catch (error) {
    console.error("[DEBUG] Erro crítico ao salvar ausências:", error);
    showToast(`Erro crítico ao salvar ausências: ${error.message}`, "error");
  } finally {
    if (btnSalvar) {
      btnSalvar.disabled = false;
      btnSalvar.innerHTML = originalButtonHtml;
      if (typeof lucide !== "undefined") lucide.createIcons();
    }
  }
}

async function excluirAusenciasNoServidor(ids, csrfToken) {
  if (!ids || ids.length === 0) {
    showToast("Nenhuma ausência selecionada para exclusão.", "info");
    return;
  }
  if (!confirm(`Tem certeza que deseja excluir ${ids.length} ausência(ões)?`))
    return;
  try {
    const response = await fetch("gerenciar_ausencias.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        acao: "excluir_ausencias",
        ids_ausencias: ids,
        csrf_token: csrfToken,
      }),
    });
    let data;
    if (!response.ok) {
      let errorMsg = `Erro do servidor: HTTP ${response.status}`;
      try {
        data = await response.json();
        errorMsg = data.message || errorMsg;
      } catch (e) {
        const errText = await response.text().catch(() => "");
        errorMsg = errText.substring(0, 150) || errorMsg;
      }
      throw new Error(errorMsg);
    }
    data = await response.json();

    if (data.success) {
      showToast(data.message || "Ausência(s) excluída(s)!", "success");
      if (data.csrf_token) {
        const csrfInput = document.getElementById("csrf-token-ausencias");
        if (csrfInput) csrfInput.value = data.csrf_token;
      }
      carregarAusenciasDoServidor(
        currentDisplayYearAusencias,
        currentDisplayMonthAusencias
      );
    } else {
      showToast(
        "Erro ao excluir ausências: " + (data.message || "Erro do servidor."),
        "error"
      );
    }
  } catch (error) {
    console.error("[DEBUG] Erro crítico ao excluir ausências:", error);
    showToast(`Erro crítico ao excluir ausências: ${error.message}.`, "error");
  }
}

function updateEscalaSabadosDisplay(ano, mes) {
  const displayElement = document.getElementById("escala-sabados-display");
  if (displayElement) {
    displayElement.dataset.year = ano;
    displayElement.dataset.month = mes;
    if (typeof lucide !== "undefined") lucide.createIcons();
  }
}

async function carregarEscalaSabados(ano, mes) {
  const tbody = document.querySelector("#escala-sabados-table tbody");
  if (!tbody) {
    console.error(
      "[DEBUG] Corpo da tabela #escala-sabados-table não encontrado."
    );
    return;
  }
  tbody.innerHTML = `<tr><td colspan="2" class="p-2 text-center text-gray-500 text-sm">Carregando... <i data-lucide="loader-circle" class="lucide-spin inline-block w-4 h-4"></i></td></tr>`;
  if (typeof lucide !== "undefined") lucide.createIcons();

  try {
    const response = await fetch(
      `carregar_escala_sabados.php?ano=${ano}&mes=${mes}`
    );
    let data;
    if (!response.ok) {
      let errorMsg = `Falha na requisição: HTTP ${response.status}`;
      try {
        data = await response.json();
        errorMsg = data.message || errorMsg;
      } catch (e) {
        const errText = await response.text().catch(() => "");
        errorMsg = errText.substring(0, 200) || errorMsg;
      }
      throw new Error(errorMsg);
    }
    data = await response.json();

    tbody.innerHTML = "";
    if (data.success && data.escala) {
      if (data.escala.length === 0) {
        const r = tbody.insertRow();
        r.className = "bg-white";
        const c = r.insertCell();
        c.colSpan = 2;
        c.className = "p-2 text-center text-gray-500 text-sm";
        c.textContent = "Nenhuma escala de sábado para este período.";
      } else {
        data.escala.forEach((item) => {
          const r = tbody.insertRow();
          r.className = "bg-white hover:bg-gray-50";
          const cellData = r.insertCell();
          cellData.className = "p-2 text-sm text-gray-700";
          cellData.textContent = item.data;
          const cellColaborador = r.insertCell();
          cellColaborador.className = "p-2 text-sm text-gray-700";
          cellColaborador.textContent = item.colaborador;
        });
      }
    } else {
      tbody.innerHTML = `<tr><td colspan="2" class="p-2 text-center text-red-500 text-sm">${
        data.message || "Erro ao carregar dados."
      }</td></tr>`;
      showToast(
        data.message || "Erro ao carregar escala de sábados.",
        "warning"
      );
    }
  } catch (error) {
    console.error("[DEBUG] Erro ao buscar escala de sábados:", error);
    tbody.innerHTML = `<tr><td colspan="2" class="p-2 text-center text-red-500 text-sm">Erro de conexão ou dados inválidos. Verifique o console.</td></tr>`;
    showToast("Erro ao carregar escala de sábados: " + error.message, "error");
  }
}

function updateAusenciaSetorDisplay(ano, mes) {
  const displayElement = document.getElementById("ausencia-setor-display");
  if (displayElement) {
    displayElement.dataset.year = ano;
    displayElement.dataset.month = mes;
    if (typeof lucide !== "undefined") lucide.createIcons();
  }
}

async function carregarAusenciaSetor(ano, mes) {
  const tbody = document.querySelector("#ausencia-setor-table tbody");
  if (!tbody) {
    console.error(
      "[DEBUG] Corpo da tabela #ausencia-setor-table não encontrado."
    );
    return;
  }
  tbody.innerHTML = `<tr><td colspan="2" class="p-2 text-center text-gray-500 text-sm">Carregando... <i data-lucide="loader-circle" class="lucide-spin inline-block w-4 h-4"></i></td></tr>`;
  if (typeof lucide !== "undefined") lucide.createIcons();

  try {
    const response = await fetch(
      `carregar_ausencia_setor.php?ano=${ano}&mes=${mes}`
    );
    let data;
    if (!response.ok) {
      let errorMsg = `Falha na requisição: HTTP ${response.status}`;
      try {
        data = await response.json();
        errorMsg = data.message || errorMsg;
      } catch (e) {
        const errText = await response.text().catch(() => "");
        errorMsg = errText.substring(0, 200) || errorMsg;
      }
      throw new Error(errorMsg);
    }
    data = await response.json();

    tbody.innerHTML = "";
    if (data.success && data.ausencias) {
      if (data.ausencias.length === 0) {
        const r = tbody.insertRow();
        r.className = "bg-white";
        const c = r.insertCell();
        c.colSpan = 2;
        c.className = "p-2 text-center text-gray-500 text-sm";
        c.textContent = "Nenhuma ausência de setor para este período.";
      } else {
        data.ausencias.forEach((item) => {
          const r = tbody.insertRow();
          r.className = "bg-white hover:bg-gray-50";
          const cellData = r.insertCell();
          cellData.className = "p-2 text-sm text-gray-700";

          // --- INÍCIO DA LÓGICA DE FORMATAÇÃO DE DATA ATUALIZADA ---
          let dataFormatada = item.data; // Valor padrão é o dado original

          if (item.data && typeof item.data === "string") {
            // Processa apenas se item.data for uma string não vazia
            try {
              const [anoItemStr, mesItemStr, diaItemStr] = item.data.split("-");
              const anoItemNum = parseInt(anoItemStr, 10);
              const mesItemNum = parseInt(mesItemStr, 10); // Mês base 1 (ex: 1 para Janeiro)
              const diaItemNum = parseInt(diaItemStr, 10);

              if (
                !isNaN(anoItemNum) &&
                !isNaN(mesItemNum) &&
                !isNaN(diaItemNum) &&
                mesItemNum >= 1 &&
                mesItemNum <= 12
              ) {
                const dataObj = new Date(
                  anoItemNum,
                  mesItemNum - 1,
                  diaItemNum
                ); // Mês base 0 para o construtor Date

                // Verifica se a data construída é válida e corresponde aos componentes
                if (
                  dataObj &&
                  dataObj.getFullYear() === anoItemNum &&
                  dataObj.getMonth() === mesItemNum - 1 &&
                  dataObj.getDate() === diaItemNum
                ) {
                  const diaSemana = [
                    "Dom",
                    "Seg",
                    "Ter",
                    "Qua",
                    "Qui",
                    "Sex",
                    "Sáb",
                  ][dataObj.getDay()];
                  // Assegura que nomesMeses[mesItemNum] existe antes de usar
                  const nomeDoMesFormatado =
                    nomesMeses[mesItemNum] || mesItemStr; // Usa o número do mês se o nome não for encontrado
                  dataFormatada = `${String(diaItemNum).padStart(
                    2,
                    "0"
                  )}/${nomeDoMesFormatado} (${diaSemana})`;
                } else {
                  console.warn(
                    `[DEBUG] Ausência Setor: Data inválida '${item.data}' resultou em objeto Date inválido. Usando valor original.`
                  );
                  // dataFormatada continua sendo item.data
                }
              } else {
                console.warn(
                  `[DEBUG] Ausência Setor: Formato de data inesperado ou valores inválidos em '${item.data}'. Usando valor original.`
                );
                // dataFormatada continua sendo item.data
              }
            } catch (e) {
              console.error(
                `[DEBUG] Ausência Setor: Erro ao formatar data '${item.data}':`,
                e
              );
              // dataFormatada continua sendo item.data (valor original)
            }
          } else if (item.data !== undefined && item.data !== null) {
            // Se item.data existe mas não é string (ou é string vazia já tratada por dataFormatada = item.data no início)
            console.warn(
              `[DEBUG] Ausência Setor: item.data não é uma string válida ou está vazio: '${JSON.stringify(
                item.data
              )}'. Verifique a origem dos dados.`
            );
          }

          // Garante que não exiba "undefined" ou "null" literalmente, mas sim uma string vazia
          cellData.textContent =
            dataFormatada === undefined || dataFormatada === null
              ? ""
              : dataFormatada;
          // --- FIM DA LÓGICA DE FORMATAÇÃO DE DATA ATUALIZADA ---

          const cellColaborador = r.insertCell();
          cellColaborador.className = "p-2 text-sm text-gray-700";
          cellColaborador.textContent = item.colaborador;
        });
      }
    } else {
      tbody.innerHTML = `<tr><td colspan="2" class="p-2 text-center text-red-500 text-sm">${
        data.message || "Erro ao carregar dados."
      }</td></tr>`;
      showToast(
        data.message || "Erro ao carregar ausências do setor.",
        "warning"
      );
    }
  } catch (error) {
    console.error("[DEBUG] Erro ao buscar ausência do setor:", error);
    tbody.innerHTML = `<tr><td colspan="2" class="p-2 text-center text-red-500 text-sm">Erro de conexão ou dados inválidos. Verifique o console.</td></tr>`;
    showToast("Erro ao carregar ausência do setor: " + error.message, "error");
  }
}

// --- Event Listeners e Código Executado no DOMContentLoaded ---
document.addEventListener("DOMContentLoaded", async function () {
  console.log("[DEBUG] DOMContentLoaded: Evento disparado.");
  console.log(
    `[DEBUG] DOMContentLoaded: Data inicial global ${currentDisplayMonth}/${currentDisplayYear}`
  );

  const displayElementInit = document.getElementById(
    "current-month-year-display"
  );
  if (
    displayElementInit &&
    displayElementInit.dataset.year &&
    displayElementInit.dataset.month
  ) {
    currentDisplayYear =
      parseInt(displayElementInit.dataset.year, 10) || currentDisplayYear;
    currentDisplayMonth =
      parseInt(displayElementInit.dataset.month, 10) || currentDisplayMonth;
    console.log(
      `[DEBUG] DOMContentLoaded: Data ajustada pelo display para ${currentDisplayMonth}/${currentDisplayYear}`
    );
  } else {
    console.warn(
      "[DEBUG] DOMContentLoaded: Elemento 'current-month-year-display' ou seus data attributes não encontrados/válidos. Usando data global padrão."
    );
  }

  currentDisplayYearAusencias = currentDisplayYear;
  currentDisplayMonthAusencias = currentDisplayMonth;
  currentDisplayYearFeriados = currentDisplayYear;
  currentDisplayMonthFeriados = currentDisplayMonth;
  currentDisplayYearEscalaSabados = currentDisplayYear;
  currentDisplayMonthEscalaSabados = currentDisplayMonth;
  currentDisplayYearAusenciaSetor = currentDisplayYear;
  currentDisplayMonthAusenciaSetor = currentDisplayMonth;

  console.log(
    "[DEBUG] DOMContentLoaded: Chamando funções de atualização de display."
  );
  updateCurrentMonthYearDisplay();
  updateCurrentMonthYearDisplayAusencias();
  updateFeriadosDisplay(
    currentDisplayYearFeriados,
    currentDisplayMonthFeriados
  );
  updateEscalaSabadosDisplay(
    currentDisplayYearEscalaSabados,
    currentDisplayMonthEscalaSabados
  );
  updateAusenciaSetorDisplay(
    currentDisplayYearAusenciaSetor,
    currentDisplayMonthAusenciaSetor
  );

  console.log(
    "[DEBUG] DOMContentLoaded: Iniciando carregamento de dados dos módulos."
  );
  if (document.getElementById("shifts-table-main")) {
    console.log("[DEBUG] DOMContentLoaded: Carregando colaboradores e turnos.");
    await buscarEArmazenarColaboradores();
    carregarTurnosDoServidor(currentDisplayYear, currentDisplayMonth);
  }

  if (document.getElementById("feriados-table")) {
    console.log("[DEBUG] DOMContentLoaded: Carregando feriados.");
    carregarFeriados(currentDisplayYearFeriados, currentDisplayMonthFeriados);
  }
  if (document.getElementById("escala-sabados-table")) {
    console.log("[DEBUG] DOMContentLoaded: Carregando escala sábados.");
    carregarEscalaSabados(
      currentDisplayYearEscalaSabados,
      currentDisplayMonthEscalaSabados
    );
  }
  if (document.getElementById("ausencia-setor-table")) {
    console.log("[DEBUG] DOMContentLoaded: Carregando ausência setor.");
    carregarAusenciaSetor(
      currentDisplayYearAusenciaSetor,
      currentDisplayMonthAusenciaSetor
    );
  }

  if (document.getElementById("ausencias-table-main")) {
    console.log(
      "[DEBUG] DOMContentLoaded: Elemento ausencias-table-main encontrado. Carregando colaboradores antes de ausências."
    );
    if (
      todosOsColaboradores.length === 0 &&
      typeof buscarEArmazenarColaboradores === "function"
    ) {
      await buscarEArmazenarColaboradores();
    }
    carregarAusenciasDoServidor(
      currentDisplayYearAusencias,
      currentDisplayMonthAusencias
    );
  } else {
    console.log(
      "[DEBUG] DOMContentLoaded: Elemento ausencias-table-main NÃO encontrado. Módulo de ausências não será carregado."
    );
  }

  const syncDatesAndReloadAll = (newYear, newMonth) => {
    console.log(
      `[DEBUG] syncDatesAndReloadAll chamado com newYear: ${newYear}, newMonth: ${newMonth}`
    );
    currentDisplayYear = newYear;
    currentDisplayMonth = newMonth;
    currentDisplayYearAusencias = newYear;
    currentDisplayMonthAusencias = newMonth;
    currentDisplayYearFeriados = newYear;
    currentDisplayMonthFeriados = newMonth;
    currentDisplayYearEscalaSabados = newYear;
    currentDisplayMonthEscalaSabados = newMonth;
    currentDisplayYearAusenciaSetor = newYear;
    currentDisplayMonthAusenciaSetor = newMonth;

    updateCurrentMonthYearDisplay();
    if (document.getElementById("shifts-table-main"))
      carregarTurnosDoServidor(currentDisplayYear, currentDisplayMonth, true);

    updateCurrentMonthYearDisplayAusencias();
    if (document.getElementById("ausencias-table-main"))
      carregarAusenciasDoServidor(
        currentDisplayYearAusencias,
        currentDisplayMonthAusencias
      );

    updateFeriadosDisplay(
      currentDisplayYearFeriados,
      currentDisplayMonthFeriados
    );
    if (document.getElementById("feriados-table"))
      carregarFeriados(currentDisplayYearFeriados, currentDisplayMonthFeriados);

    updateEscalaSabadosDisplay(
      currentDisplayYearEscalaSabados,
      currentDisplayMonthEscalaSabados
    );
    if (document.getElementById("escala-sabados-table"))
      carregarEscalaSabados(
        currentDisplayYearEscalaSabados,
        currentDisplayMonthEscalaSabados
      );

    updateAusenciaSetorDisplay(
      currentDisplayYearAusenciaSetor,
      currentDisplayMonthAusenciaSetor
    );
    if (document.getElementById("ausencia-setor-table"))
      carregarAusenciaSetor(
        currentDisplayYearAusenciaSetor,
        currentDisplayMonthAusenciaSetor
      );
  };

  const prevMonthButton = document.getElementById("prev-month-button");
  if (prevMonthButton) {
    prevMonthButton.addEventListener("click", () => {
      console.log("[DEBUG] Botão 'Anterior' (Turnos/Geral) clicado.");
      let newMonth = currentDisplayMonth - 1;
      let newYear = currentDisplayYear;
      if (newMonth < 1) {
        newMonth = 12;
        newYear--;
      }
      syncDatesAndReloadAll(newYear, newMonth);
    });
  }

  const nextMonthButton = document.getElementById("next-month-button");
  if (nextMonthButton) {
    nextMonthButton.addEventListener("click", () => {
      console.log("[DEBUG] Botão 'Próximo' (Turnos/Geral) clicado.");
      let newMonth = currentDisplayMonth + 1;
      let newYear = currentDisplayYear;
      if (newMonth > 12) {
        newMonth = 1;
        newYear++;
      }
      syncDatesAndReloadAll(newYear, newMonth);
    });
  }

  const prevMonthBtnAus = document.getElementById(
    "prev-month-ausencias-button"
  );
  if (
    prevMonthBtnAus &&
    (!prevMonthButton || prevMonthBtnAus !== prevMonthButton)
  ) {
    prevMonthBtnAus.addEventListener("click", () => {
      console.log("[DEBUG] Botão 'Anterior' (Ausências Específico) clicado.");
      let newMonth = currentDisplayMonthAusencias - 1;
      let newYear = currentDisplayYearAusencias;
      if (newMonth < 1) {
        newMonth = 12;
        newYear--;
      }
      syncDatesAndReloadAll(newYear, newMonth);
    });
  }

  const nextMonthBtnAus = document.getElementById(
    "next-month-ausencias-button"
  );
  if (
    nextMonthBtnAus &&
    (!nextMonthButton || nextMonthBtnAus !== nextMonthButton)
  ) {
    nextMonthBtnAus.addEventListener("click", () => {
      console.log("[DEBUG] Botão 'Próximo' (Ausências Específico) clicado.");
      let newMonth = currentDisplayMonthAusencias + 1;
      let newYear = currentDisplayYearAusencias;
      if (newMonth > 12) {
        newMonth = 1;
        newYear++;
      }
      syncDatesAndReloadAll(newYear, newMonth);
    });
  }

  const btnSalvarTurnos = document.getElementById("save-shifts-button");
  if (btnSalvarTurnos) {
    btnSalvarTurnos.addEventListener("click", () => {
      console.log("[DEBUG] Botão 'Salvar Turnos' clicado.");
      const csrfTokenEl = document.getElementById("csrf-token-shifts");
      const csrfToken = csrfTokenEl ? csrfTokenEl.value : null;
      if (!csrfToken) {
        showToast(
          "Erro de segurança (token turnos ausente). Recarregue a página.",
          "error"
        );
        return;
      }
      const dados = coletarDadosDaTabelaDeTurnos();
      if (dados && dados.length > 0) {
        salvarDadosTurnosNoServidor(dados, csrfToken);
      } else if (dados && dados.length === 0) {
        const tbody = document.querySelector("#shifts-table-main tbody");
        const placeholderVisivel =
          tbody && tbody.querySelector("td[colspan='5']");
        if (placeholderVisivel || (tbody && tbody.rows.length === 0)) {
          showToast("Adicione pelo menos um turno para salvar.", "info");
        } else {
          showToast(
            "Nenhum turno válido para salvar. Verifique as linhas.",
            "warning"
          );
        }
      } else if (dados === null) {
        console.log(
          "[DEBUG] Coleta de dados de turnos retornou null (erro de validação)."
        );
      } else {
        console.error(
          "[DEBUG] coletarDadosDaTabelaDeTurnos retornou valor inesperado:",
          dados
        );
        showToast("Erro interno ao coletar dados dos turnos.", "error");
      }
    });
  }

  const btnAdicionarTurno = document.getElementById("add-shift-row-button");
  if (btnAdicionarTurno) {
    btnAdicionarTurno.addEventListener("click", async function () {
      console.log("[DEBUG] Botão 'Adicionar Turno' clicado.");
      const tbody = document.querySelector("#shifts-table-main tbody");
      if (!tbody) return;
      const placeholderRow = tbody.querySelector("td[colspan='5']");
      if (placeholderRow) tbody.innerHTML = "";

      if (
        todosOsColaboradores.length === 0 ||
        !todosOsColaboradores[0] ||
        !todosOsColaboradores[0].hasOwnProperty("id")
      ) {
        await buscarEArmazenarColaboradores();
      }

      const newId = "new-" + Date.now();
      const nLinha = tbody.insertRow();
      nLinha.className = "bg-white hover:bg-gray-50";
      nLinha.setAttribute("data-turno-id", newId);

      let cell = nLinha.insertCell();
      cell.className = "p-2 text-center";
      let inputChk = document.createElement("input");
      inputChk.type = "checkbox";
      inputChk.className = `shift-select-checkbox ${tailwindCheckboxClasses}`;
      cell.appendChild(inputChk);

      cell = nLinha.insertCell();
      cell.className = "p-1";
      let inputData = document.createElement("input");
      inputData.type = "text";
      inputData.className = `shift-date ${tailwindInputClasses}`;
      inputData.placeholder = "dd/Mês";
      cell.appendChild(inputData);
      inputData.focus();

      cell = nLinha.insertCell();
      cell.className = "p-1";
      let inputInicio = document.createElement("input");
      inputInicio.type = "time";
      inputInicio.className = `shift-time-inicio ${tailwindInputClasses}`;
      cell.appendChild(inputInicio);

      cell = nLinha.insertCell();
      cell.className = "p-1";
      let inputFim = document.createElement("input");
      inputFim.type = "time";
      inputFim.className = `shift-time-fim ${tailwindInputClasses}`;
      cell.appendChild(inputFim);

      cell = nLinha.insertCell();
      cell.className = "p-1";
      const selColab = document.createElement("select");
      selColab.className = `shift-employee shift-employee-select ${tailwindSelectClasses}`;
      popularSelectColaborador(selColab);
      cell.appendChild(selColab);
    });
  }

  const chkAllShifts = document.getElementById("select-all-shifts");
  if (chkAllShifts) {
    chkAllShifts.addEventListener("change", () => {
      document
        .querySelectorAll("#shifts-table-main .shift-select-checkbox")
        .forEach((c) => (c.checked = chkAllShifts.checked));
    });
  }

  const btnDelSelShifts = document.getElementById(
    "delete-selected-shifts-button"
  );
  if (btnDelSelShifts) {
    btnDelSelShifts.addEventListener("click", () => {
      console.log("[DEBUG] Botão 'Excluir Turnos Selecionados' clicado.");
      const csrfTokenEl = document.getElementById("csrf-token-shifts");
      const csrfToken = csrfTokenEl ? csrfTokenEl.value : null;
      if (!csrfToken) {
        showToast("Erro de segurança. Recarregue a página.", "error");
        return;
      }
      const idsParaExcluirServidor = [];
      let linhasNovasRemovidasLocalmente = 0;

      document
        .querySelectorAll("#shifts-table-main .shift-select-checkbox:checked")
        .forEach((c) => {
          const tr = c.closest("tr");
          if (tr) {
            const id = tr.getAttribute("data-turno-id");
            if (id && !id.startsWith("new-")) {
              idsParaExcluirServidor.push(id);
            } else if (id && id.startsWith("new-")) {
              tr.remove();
              linhasNovasRemovidasLocalmente++;
            }
          }
        });

      if (idsParaExcluirServidor.length > 0) {
        excluirTurnosNoServidor(idsParaExcluirServidor, csrfToken);
      }
      if (linhasNovasRemovidasLocalmente > 0) {
        showToast(
          `${linhasNovasRemovidasLocalmente} linha(s) nova(s) (não salva(s)) foram removida(s).`,
          "info"
        );
        const tbody = document.querySelector("#shifts-table-main tbody");
        if (tbody && tbody.rows.length === 0) {
          popularTabelaTurnos([]);
        }
      }
      if (
        idsParaExcluirServidor.length === 0 &&
        linhasNovasRemovidasLocalmente === 0
      ) {
        showToast("Nenhum turno selecionado para exclusão.", "info");
      }
      if (chkAllShifts) chkAllShifts.checked = false;
    });
  }

  const btnAddAusencia = document.getElementById("add-ausencia-row-button");
  if (btnAddAusencia) {
    btnAddAusencia.addEventListener("click", async function () {
      console.log("[DEBUG] Botão 'Adicionar Ausência' clicado.");
      const tbody = document.querySelector("#ausencias-table-main tbody");
      if (!tbody) return;
      const placeholderRow = tbody.querySelector("td[colspan='5']");
      if (placeholderRow) tbody.innerHTML = "";

      if (
        todosOsColaboradores.length === 0 ||
        !todosOsColaboradores[0] ||
        !todosOsColaboradores[0].hasOwnProperty("id")
      ) {
        await buscarEArmazenarColaboradores();
      }

      const newId = "new-" + Date.now();
      const nLinha = tbody.insertRow();
      nLinha.className = "bg-white hover:bg-gray-50";
      nLinha.setAttribute("data-ausencia-id", newId);

      let cell = nLinha.insertCell();
      cell.className = "p-2 text-center";
      let inputChk = document.createElement("input");
      inputChk.type = "checkbox";
      inputChk.className = `ausencia-select-checkbox ${tailwindCheckboxClasses}`;
      cell.appendChild(inputChk);

      cell = nLinha.insertCell();
      cell.className = "p-1";
      let inputDI = document.createElement("input");
      inputDI.type = "date";
      inputDI.className = `ausencia-data-inicio ${tailwindInputClasses}`;
      cell.appendChild(inputDI);
      inputDI.focus();

      cell = nLinha.insertCell();
      cell.className = "p-1";
      let inputDF = document.createElement("input");
      inputDF.type = "date";
      inputDF.className = `ausencia-data-fim ${tailwindInputClasses}`;
      cell.appendChild(inputDF);

      cell = nLinha.insertCell();
      cell.className = "p-1";
      const selColabAusencia = document.createElement("select");
      selColabAusencia.className = `ausencia-colaborador ${tailwindSelectClasses}`;
      popularSelectColaborador(selColabAusencia);
      cell.appendChild(selColabAusencia);

      cell = nLinha.insertCell();
      cell.className = "p-1";
      let inputObs = document.createElement("input");
      inputObs.type = "text";
      inputObs.className = `ausencia-observacoes ${tailwindInputClasses}`;
      inputObs.placeholder = "Motivo/Observações da ausência";
      cell.appendChild(inputObs);
    });
  }
  const btnSalvarAusencias = document.getElementById("save-ausencias-button");
  if (btnSalvarAusencias) {
    btnSalvarAusencias.addEventListener("click", () => {
      console.log("[DEBUG] Botão 'Salvar Ausências' clicado.");
      const csrfTokenEl = document.getElementById("csrf-token-ausencias");
      const csrfToken = csrfTokenEl ? csrfTokenEl.value : null;
      if (!csrfToken) {
        showToast(
          "Erro de segurança (token ausências ausente). Recarregue.",
          "error"
        );
        return;
      }
      const dados = coletarDadosDaTabelaDeAusencias();
      console.log("[DEBUG] Dados coletados para salvar ausências:", dados);
      if (dados && dados.length > 0) {
        salvarDadosAusenciasNoServidor(dados, csrfToken);
      } else if (dados && dados.length === 0) {
        const tbody = document.querySelector("#ausencias-table-main tbody");
        const placeholderVisivel =
          tbody && tbody.querySelector("td[colspan='5']");
        if (placeholderVisivel || (tbody && tbody.rows.length === 0)) {
          showToast("Adicione uma ausência para salvar.", "info");
        } else {
          showToast(
            "Nenhuma ausência válida para salvar. Verifique as linhas.",
            "warning"
          );
        }
      } else if (dados === null) {
        console.log(
          "[DEBUG] Coleta de dados de ausências retornou null (erro de validação)."
        );
      } else {
        console.error(
          "[DEBUG] coletarDadosDaTabelaDeAusencias retornou valor inesperado:",
          dados
        );
        showToast("Erro interno ao coletar dados das ausências.", "error");
      }
    });
  }
  const chkAllAus = document.getElementById("select-all-ausencias");
  if (chkAllAus) {
    chkAllAus.addEventListener("change", () => {
      document
        .querySelectorAll("#ausencias-table-main .ausencia-select-checkbox")
        .forEach((c) => (c.checked = chkAllAus.checked));
    });
  }
  const btnDelSelAus = document.getElementById(
    "delete-selected-ausencias-button"
  );
  if (btnDelSelAus) {
    btnDelSelAus.addEventListener("click", () => {
      console.log("[DEBUG] Botão 'Excluir Ausências Selecionadas' clicado.");
      const csrfTokenEl = document.getElementById("csrf-token-ausencias");
      const csrfToken = csrfTokenEl ? csrfTokenEl.value : null;
      if (!csrfToken) {
        showToast("Erro de segurança (token ausências). Recarregue.", "error");
        return;
      }
      const idsParaExcluirServidor = [];
      let linhasNovasRemovidasLocalmente = 0;

      document
        .querySelectorAll(
          "#ausencias-table-main .ausencia-select-checkbox:checked"
        )
        .forEach((c) => {
          const tr = c.closest("tr");
          if (tr) {
            const id = tr.getAttribute("data-ausencia-id");
            if (id && !id.startsWith("new-")) {
              idsParaExcluirServidor.push(id);
            } else if (id && id.startsWith("new-")) {
              tr.remove();
              linhasNovasRemovidasLocalmente++;
            }
          }
        });

      if (idsParaExcluirServidor.length > 0) {
        excluirAusenciasNoServidor(idsParaExcluirServidor, csrfToken);
      }
      if (linhasNovasRemovidasLocalmente > 0) {
        showToast(
          `${linhasNovasRemovidasLocalmente} linha(s) nova(s) (não salva(s)) foram removida(s).`,
          "info"
        );
        const tbody = document.querySelector("#ausencias-table-main tbody");
        if (tbody && tbody.rows.length === 0) {
          popularTabelaAusencias([]);
        }
      }

      if (
        idsParaExcluirServidor.length === 0 &&
        linhasNovasRemovidasLocalmente === 0
      ) {
        showToast("Nenhuma ausência selecionada para exclusão.", "info");
      }
      if (chkAllAus) chkAllAus.checked = false;
    });
  }

  const salvarObsBtn = document.getElementById("salvar-observacoes-gerais-btn");
  const obsGeralTextarea = document.getElementById(
    "observacoes-gerais-textarea"
  );
  if (salvarObsBtn && obsGeralTextarea) {
    carregarObservacaoGeral();
    salvarObsBtn.addEventListener("click", salvarObservacaoGeral);
  }

  const logoutLk = document.getElementById("logout-link");
  if (logoutLk) {
    logoutLk.addEventListener("click", (e) => {
      e.preventDefault();
      showToast("Saindo do sistema...", "info", 1500);
      setTimeout(() => {
        if (logoutLk.href) window.location.href = logoutLk.href;
      }, 1500);
    });
  }

  const backupDbBtn = document.getElementById("backup-db-btn");
  const csrfTokenBackupInput = document.getElementById("csrf-token-backup");
  const backupModalBackdrop = document.getElementById("backup-modal-backdrop");
  const backupModalMessage = document.getElementById("backup-modal-message");
  const backupModalCloseBtn = document.getElementById("backup-modal-close-btn");
  const backupProgressBarContainer = document.getElementById(
    "backup-progress-bar-container"
  );
  const backupProgressBar = document.getElementById("backup-progress-bar");
  const backupDownloadLink = document.getElementById("backup-download-link");
  let originalBackupBtnHTML = "";

  function showBackupModal(
    message,
    isLoading,
    isError,
    isSuccess,
    elMsg,
    elProgressContainer,
    elProgress,
    elDownloadLink,
    elCloseBtn,
    elBackdrop
  ) {
    if (elBackdrop) elBackdrop.classList.add("show");
    if (elMsg) elMsg.textContent = message;

    if (elProgressContainer)
      elProgressContainer.style.display = isLoading ? "block" : "none";
    if (elProgress && isLoading) {
      elProgress.style.width = "0%";
      setTimeout(() => (elProgress.style.width = "100%"), 100);
    }

    if (elMsg) {
      elMsg.classList.remove("text-green-600", "text-red-600", "text-gray-700");
      if (isSuccess) elMsg.classList.add("text-green-600");
      else if (isError) elMsg.classList.add("text-red-600");
      else elMsg.classList.add("text-gray-700");
    }

    if (elDownloadLink) elDownloadLink.classList.add("hidden");
    if (elCloseBtn) elCloseBtn.style.display = "none";
  }

  if (backupDbBtn && csrfTokenBackupInput) {
    originalBackupBtnHTML = backupDbBtn.innerHTML;

    if (backupModalCloseBtn && backupModalBackdrop) {
      backupModalCloseBtn.addEventListener("click", () => {
        backupModalBackdrop.classList.remove("show");
        if (backupDbBtn && originalBackupBtnHTML) {
          backupDbBtn.disabled = false;
          backupDbBtn.innerHTML = originalBackupBtnHTML;
          if (typeof lucide !== "undefined") lucide.createIcons();
        }
      });
    }

    backupDbBtn.addEventListener("click", async function (event) {
      event.preventDefault();
      if (backupDbBtn.disabled) return;
      if (
        !confirm("Tem certeza que deseja iniciar o backup do banco de dados?")
      )
        return;

      if (
        backupModalBackdrop &&
        backupModalMessage &&
        backupProgressBarContainer &&
        backupProgressBar &&
        backupDownloadLink &&
        backupModalCloseBtn
      ) {
        showBackupModal(
          "Iniciando backup, por favor aguarde...",
          true,
          false,
          false,
          backupModalMessage,
          backupProgressBarContainer,
          backupProgressBar,
          backupDownloadLink,
          backupModalCloseBtn,
          backupModalBackdrop
        );
      }

      backupDbBtn.disabled = true;
      backupDbBtn.innerHTML = `<i data-lucide="loader-circle" class="animate-spin w-4 h-4 mr-2"></i> Processando...`;
      if (typeof lucide !== "undefined") lucide.createIcons();
      const csrfToken = csrfTokenBackupInput.value;

      try {
        const response = await fetch("backup_database.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            action: "create_backup",
            csrf_token_backup: csrfToken,
          }),
        });
        let data;
        if (!response.ok) {
          let errorMsg = `Servidor respondeu com erro: HTTP ${response.status}`;
          try {
            data = await response.json();
            errorMsg = data.message || errorMsg;
          } catch (e) {
            const errText = await response.text().catch(() => "");
            errorMsg = errText.substring(0, 150) || errorMsg;
          }
          throw new Error(errorMsg);
        }
        data = await response.json();

        if (data.success) {
          if (
            backupModalBackdrop &&
            backupModalMessage &&
            backupProgressBarContainer &&
            backupProgressBar &&
            backupDownloadLink &&
            backupModalCloseBtn
          ) {
            showBackupModal(
              data.message || "Backup concluído!",
              false,
              false,
              true,
              backupModalMessage,
              backupProgressBarContainer,
              backupProgressBar,
              backupDownloadLink,
              backupModalCloseBtn,
              backupModalBackdrop
            );
            if (backupModalCloseBtn)
              backupModalCloseBtn.style.display = "inline-flex";
            if (data.download_url && backupDownloadLink) {
              backupDownloadLink.href = data.download_url;
              backupDownloadLink.classList.remove("hidden");
            } else if (
              data.filename &&
              !data.download_url &&
              backupDownloadLink
            ) {
              backupDownloadLink.href = `download_backup_file.php?file=${encodeURIComponent(
                data.filename
              )}`;
              backupDownloadLink.classList.remove("hidden");
            } else {
              if (backupDownloadLink)
                backupDownloadLink.classList.add("hidden");
              showToast(
                "URL de download não fornecida pelo servidor.",
                "warning"
              );
            }
          }
        } else {
          const errorMsg = data.message || "Falha no backup.";
          if (
            backupModalBackdrop &&
            backupModalMessage &&
            backupProgressBarContainer &&
            backupProgressBar &&
            backupDownloadLink &&
            backupModalCloseBtn
          ) {
            showBackupModal(
              "Erro: " + errorMsg,
              false,
              true,
              false,
              backupModalMessage,
              backupProgressBarContainer,
              backupProgressBar,
              backupDownloadLink,
              backupModalCloseBtn,
              backupModalBackdrop
            );
            if (backupModalCloseBtn)
              backupModalCloseBtn.style.display = "inline-flex";
          }
          showToast("Falha no backup: " + errorMsg, "error", 7000);
        }
      } catch (error) {
        console.error("[DEBUG] Erro requisição de backup:", error);
        if (
          backupModalBackdrop &&
          backupModalMessage &&
          backupProgressBarContainer &&
          backupProgressBar &&
          backupDownloadLink &&
          backupModalCloseBtn
        ) {
          showBackupModal(
            "Erro de comunicação ao tentar backup. Verifique o console.",
            false,
            true,
            false,
            backupModalMessage,
            backupProgressBarContainer,
            backupProgressBar,
            backupDownloadLink,
            backupModalCloseBtn,
            backupModalBackdrop
          );
          if (backupModalCloseBtn)
            backupModalCloseBtn.style.display = "inline-flex";
        }
        showToast("Erro de comunicação: " + error.message, "error");
      }
    });
  } else {
    if (!backupDbBtn)
      console.warn("[DEBUG] Botão de backup (backup-db-btn) não encontrado.");
    if (!csrfTokenBackupInput)
      console.warn(
        "[DEBUG] Campo CSRF de backup (csrf-token-backup) não encontrado."
      );
  }

  console.log(
    "[DEBUG] DOMContentLoaded: Todos os listeners e carregamentos iniciais configurados."
  );
  if (typeof lucide !== "undefined") {
    lucide.createIcons();
  } else {
    console.warn("[DEBUG] Biblioteca Lucide (lucide.js) não está definida.");
  }
});

console.log("[DEBUG] script.js: Fim da análise do script.");
