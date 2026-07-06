/**
 * 設定ページ用JavaScript - Gemini Edition
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    // モデル説明文の保持用
    let modelDescriptions = picot_seo_writing_admin.model_descriptions || {};

    function updateDescription($select, $descDiv) {
      if (!$select.length || !$descDiv.length) return;
      const val = $select.val();
      const desc = modelDescriptions[val] || '';
      $descDiv.text(desc);
    }

    // モデル選択変更時のイベント
    $(document).on('change', '#picot_seo_writing_text_model', function () {
      updateDescription($(this), $('#picot_seo_writing_text_model_description'));
    });
    $(document).on('change', '#picot_seo_writing_image_model', function () {
      updateDescription($(this), $('#picot_seo_writing_image_model_description'));
    });

    // 初期化実行
    updateDescription($('#picot_seo_writing_text_model'), $('#picot_seo_writing_text_model_description'));
    updateDescription($('#picot_seo_writing_image_model'), $('#picot_seo_writing_image_model_description'));

    // Geminiモデル一覧取得ボタン
    $("#fetch-gemini-models").on("click", function () {
      const $button = $(this);
      const $select = $("#picot_seo_writing_text_model");
      const originalText = $button.text();

      $button.prop("disabled", true).text(picot_seo_writing_admin.strings.fetching || "取得中...");

      $.ajax({
        url: picot_seo_writing_admin.ajax_url,
        method: "POST",
        data: {
          action: "picot_seo_writing_fetch_gemini_models",
          nonce: picot_seo_writing_admin.ajax_nonce,
        },
        success: function (response) {
          if (response.success && response.data.models) {
            // 説明文データの更新
            if (response.data.descriptions) {
              modelDescriptions = response.data.descriptions;
            }

            // テキストモデルの更新
            const currentValue = $select.val();
            $select.empty();
            $.each(response.data.models, function (id, label) {
              $select.append(
                $("<option></option>").attr("value", id).text(label),
              );
            });
            if ($select.find('option[value="' + currentValue + '"]').length > 0) {
              $select.val(currentValue);
            }
            updateDescription($select, $('#picot_seo_writing_text_model_description'));

            // 画像モデルの更新
            const $imgSelect = $("#picot_seo_writing_image_model");
            if ($imgSelect.length && response.data.image_models) {
              const currentImgValue = $imgSelect.val();
              $imgSelect.empty();
              $.each(response.data.image_models, function (id, label) {
                $imgSelect.append(
                  $("<option></option>").attr("value", id).text(label),
                );
              });
              if ($imgSelect.find('option[value="' + currentImgValue + '"]').length > 0) {
                $imgSelect.val(currentImgValue);
              }
              updateDescription($imgSelect, $('#picot_seo_writing_image_model_description'));
            }

            alert(picot_seo_writing_admin.strings.updateSuccess || "モデル一覧を更新しました");
          } else {
            alert(response.data.message || "取得に失敗しました");
          }
        },
        error: function () {
          alert(picot_seo_writing_admin.strings.updateFailed || "モデル一覧の取得に失敗しました");
        },
        complete: function () {
          $button.prop("disabled", false).text(originalText);
        },
      });
    });

    // フォーカス時にパスワードを表示（APIキー入力用）
    $(document).on("focus", ".picot-hover-show", function () {
      $(this).attr("type", "text");
    }).on("blur", ".picot-hover-show", function () {
      $(this).attr("type", "password");
    });

    function getErrorMessage(response, fallback) {
      if (response && response.data) {
        if (typeof response.data.message === "string" && response.data.message) {
          return response.data.message;
        }
        if (typeof response.data === "string" && response.data) {
          return response.data;
        }
      }
      return fallback;
    }

    // 接続テストボタン
    $(document).on("click", ".picot-test-connection-btn", function () {
      const btn = $(this);
      const provider = btn.data("provider") || "ai";
      const resultSpan = $("#picot-test-result-" + provider);
      const originalText = btn.text();

      btn.prop("disabled", true).text("テスト中...");
      resultSpan.text("通信中...").css("color", "#444");

      $.ajax({
        url: picot_seo_writing_admin.ajax_url,
        type: "POST",
        dataType: "json",
        data: {
          action: "picot_seo_writing_test_connection",
          nonce: picot_seo_writing_admin.ajax_nonce,
        },
      })
        .done(function (response) {
          if (response && response.success) {
            resultSpan.text(getErrorMessage(response, "✅ 接続に成功しました")).css("color", "#155724");
          } else {
            resultSpan
              .text(getErrorMessage(response, "❌ 接続に失敗しました"))
              .css("color", "#d63638");
          }
        })
        .fail(function (xhr) {
          let message = "❌ 通信エラーが発生しました";
          if (xhr && xhr.responseText) {
            try {
              const parsed = JSON.parse(xhr.responseText);
              message = getErrorMessage(parsed, message);
            } catch (e) {
              if (xhr.responseText === "-1" || xhr.responseText === "0") {
                message = "❌ セッションが切れています。ページを再読み込みしてください。";
              }
            }
          }
          resultSpan.text(message).css("color", "#d63638");
        })
        .always(function () {
          btn.prop("disabled", false).text(originalText);
        });
    });
  });
})(jQuery);
