if (typeof window.adminInitialized === "undefined") {
  window.adminInitialized = true;

  // Variáveis globais para ordenação
  let currentOrderBy = null;
  let currentOrderDir = "ASC";

  $(document).ready(function () {
    // Configuração inicial da interface
    const slider = $("#sliderBar");
    const btnProdutos = $("#btnProdutos");
    const btnUsuarios = $("#btnUsuarios");

    function moveSlider(toLeft) {
      slider.css("transform", toLeft ? "translateX(0%)" : "translateX(100%)");
    }

    function updateSliderVisibility() {
      slider.toggle($(".btn-admin.active").length > 0);
    }

    function activateButton(activeId) {
      $(".btn-admin").removeClass("active");
      $("#" + activeId).addClass("active");
      updateSliderVisibility();
    }

    // Eventos dos botões principais
    btnProdutos.click(function () {
      $("#usuariosContent").hide();
      $("#produtosContent").fadeIn();
      $("#msginicial").hide();
      activateButton("btnProdutos");
      moveSlider(true);
    });

    btnUsuarios.click(function () {
      $("#produtosContent").hide();
      $("#usuariosContent").fadeIn();
      $("#msginicial").hide();
      activateButton("btnUsuarios");
      moveSlider(false);
    });

    // Estado inicial
    $("#produtosContent, #usuariosContent").hide();
    $("#msginicial").show();
    $(".btn-admin").removeClass("active");
    slider.hide();

    // Carregamento de dados das tabelas
    $(document).on("click", ".btn-dado", function () {
      $(".btn-dado").removeClass("active");
      $(this).addClass("active");

      const tipo = $(this).data("tipo");
      const $targetDiv = $(this)
        .closest(".admin-section")
        .find('div[id^="dadosTabela"]');

      $targetDiv.html(
        "<p>Carregando dados de <strong>" + tipo + "</strong>...</p>"
      );

      $.ajax({
        url: window.location.href,
        type: "GET",
        data: { ajax: 1, tipo: tipo },
        success: function (resposta) {
          $targetDiv.html(resposta);
        },
        error: function () {
          $targetDiv.html(
            '<p class="text-danger">Erro ao carregar os dados.</p>'
          );
        },
      });
    });

    // Filtros
    $(document).on("input", ".filtro-coluna", function () {
      const $row = $(this).closest("tr");
      const filtros = {};
      const tipo = $(".btn-dado.active").data("tipo");
      const $targetDiv = $(this)
        .closest(".admin-section")
        .find('div[id^="dadosTabela"]');
      const colunaFocada = $(this).data("coluna");

      $row.find(".filtro-coluna").each(function () {
        const coluna = $(this).data("coluna");
        const valor = $(this).val();
        if (valor) filtros[coluna] = valor;
      });

      $.ajax({
        url: window.location.href,
        type: "GET",
        data: {
          ajax: 1,
          tipo: tipo,
          filtros: JSON.stringify(filtros),
          orderBy: currentOrderBy,
          orderDir: currentOrderDir,
        },
        success: function (resposta) {
          $targetDiv.html(resposta);
          $targetDiv.find(".filtro-coluna").each(function () {
            const coluna = $(this).data("coluna");
            if (filtros[coluna]) $(this).val(filtros[coluna]);
            if (coluna === colunaFocada) $(this).focus();
          });
        },
        error: function () {
          $targetDiv.html(
            '<p class="text-danger">Erro ao carregar os dados.</p>'
          );
        },
      });
    });

    // Modal para adicionar registro
    $(document).on("click", "#btnAddRegistro", function () {
      const tipo = $(".btn-dado.active").data("tipo");
      if (!tipo) {
        alert("Selecione uma tabela primeiro.");
        return;
      }

      $.ajax({
        url: window.location.href,
        type: "GET",
        data: { ajax: 1, tipo: tipo, getCols: 1 },
        success: function (resposta) {
          try {
            const cols = JSON.parse(resposta);

            // Verifica se recebemos colunas válidas
            if (!Array.isArray(cols)) {
              throw new Error("Formato de colunas inválido");
            }

            // Filtra colunas vazias ou inválidas
            const validCols = cols.filter(
              (col) => col && typeof col === "string" && col.trim() !== ""
            );

            // Verifica se há colunas para mostrar
            if (validCols.length === 0) {
              throw new Error("Nenhuma coluna válida encontrada");
            }

            let html = "";
            validCols.forEach(function (col) {
              html += `
                        <div class="mb-3">
                            <label for="input_${col}" class="form-label">${col}</label>
                            <input type="text" class="form-control" id="input_${col}" name="${col}" required>
                        </div>
                    `;
            });

            $("#formCampos").html(html);
            $("#formMensagem").html("");
            $("#modalAddRegistro").modal("show");
          } catch (e) {
            console.error("Erro ao montar formulário:", e);
            $("#formMensagem").html(`
                    <div class="alert alert-danger">
                        Erro ao carregar formulário: ${e.message}
                    </div>
                `);
          }
        },
        error: function (xhr, status, error) {
          $("#formMensagem").html(`
                <div class="alert alert-danger">
                    Erro ao obter campos: ${error}
                </div>
            `);
        },
      });
    });

    $("#formAddRegistro").on("submit", function (e) {
      e.preventDefault();
      const tipo = $(".btn-dado.active").data("tipo");
      if (!tipo) return;

      const dados = {};
      $(this)
        .find("input")
        .each(function () {
          dados[$(this).attr("name")] = $(this).val();
        });

      $.ajax({
        url: window.location.href,
        type: "POST",
        data: {
          action: "addRegistro",
          tipo: tipo,
          dados: JSON.stringify(dados),
        },
        success: function (resposta) {
          if (resposta === "success") {
            $("#formMensagem").html(
              '<div class="alert alert-success">Registro adicionado com sucesso!</div>'
            );
            setTimeout(() => {
              $("#modalAddRegistro").modal("hide");
              $(".btn-dado.active").click();
            }, 1500);
          } else {
            $("#formMensagem").html(
              '<div class="alert alert-danger">Erro: ' + resposta + "</div>"
            );
          }
        },
        error: function (xhr, status, error) {
          $("#formMensagem").html(
            '<div class="alert alert-danger">Erro na requisição: ' +
              error +
              "</div>"
          );
        },
      });
    });
  });
}
