/**
 * 設定ページ用JavaScript - Gemini Edition
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    const strings = (picot_seo_writing_admin && picot_seo_writing_admin.strings) || {};

    function s(key, fallback) {
      return strings[key] || fallback;
    }

    let modelDescriptions = picot_seo_writing_admin.model_descriptions || {};

    function updateDescription($select, $descDiv) {
      if (!$select.length || !$descDiv.length) return;
      const val = $select.val();
      const desc = modelDescriptions[val] || '';
      $descDiv.text(desc);
    }

    function getRecommendedModelLabel(models) {
      let fallback = "";
      let recommended = "";
      $.each(models || {}, function (id, label) {
        if (!fallback) {
          fallback = label;
        }
        if (!recommended) {
          const haystack = String(id) + " " + String(label);
          if (haystack.toLowerCase().indexOf("-lite") !== -1) {
            recommended = label;
          }
        }
      });
      return recommended || fallback;
    }

    function updateRecommendedModelText(models, selector, templateKey, templateFallback) {
      const $el = $(selector);
      if (!$el.length) {
        return;
      }
      const label = getRecommendedModelLabel(models);
      if (!label) {
        return;
      }
      const template = s(templateKey, templateFallback);
      const safeLabel = $("<div>").text(label).html();
      $el.html(template.replace("%s", "<strong>" + safeLabel + "</strong>"));
    }

    $(document).on('change', '#picot_seo_writing_text_model', function () {
      updateDescription($(this), $('#picot_seo_writing_text_model_description'));
    });
    $(document).on('change', '#picot_seo_writing_image_model', function () {
      updateDescription($(this), $('#picot_seo_writing_image_model_description'));
    });

    updateDescription($('#picot_seo_writing_text_model'), $('#picot_seo_writing_text_model_description'));
    updateDescription($('#picot_seo_writing_image_model'), $('#picot_seo_writing_image_model_description'));

    $("#fetch-gemini-models").on("click", function () {
      const $button = $(this);
      const $select = $("#picot_seo_writing_text_model");
      const originalText = $button.text();

      $button.prop("disabled", true).text(s("fetching", "Fetching..."));

      $.ajax({
        url: picot_seo_writing_admin.ajax_url,
        method: "POST",
        data: {
          action: "picot_seo_writing_fetch_gemini_models",
          nonce: picot_seo_writing_admin.ajax_nonce,
        },
        success: function (response) {
          if (response.success && response.data.models) {
            if (response.data.descriptions) {
              modelDescriptions = response.data.descriptions;
            }

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
            updateRecommendedModelText(
              response.data.models,
              ".picot-recommended-text-model",
              "recommendedModel",
              "Recommended model: %s"
            );

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
              updateRecommendedModelText(
                response.data.image_models,
                ".picot-recommended-image-model",
                "recommendedImageModel",
                "Recommended image model: %s"
              );
            }

            alert(s("updateSuccess", "Model list updated"));
          } else {
            alert(response.data.message || s("fetchFailedGeneric", "Fetch failed"));
          }
        },
        error: function () {
          alert(s("updateFailed", "Failed to fetch model list"));
        },
        complete: function () {
          $button.prop("disabled", false).text(originalText);
        },
      });
    });

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

    $(document).on("click", ".picot-test-connection-btn", function () {
      const btn = $(this);
      const provider = btn.data("provider") || "ai";
      const resultSpan = $("#picot-test-result-" + provider);
      const originalText = btn.text();

      btn.prop("disabled", true).text(s("testingConnection", "Testing..."));
      resultSpan.text(s("communicating", "Connecting...")).css("color", "#444");

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
            resultSpan.text(getErrorMessage(response, s("connectionSuccess", "Connection successful"))).css("color", "#155724");
          } else {
            resultSpan
              .text(getErrorMessage(response, s("connectionFailed", "Connection failed")))
              .css("color", "#d63638");
          }
        })
        .fail(function (xhr) {
          let message = s("communicationErrorIcon", "Communication error");
          if (xhr && xhr.responseText) {
            try {
              const parsed = JSON.parse(xhr.responseText);
              message = getErrorMessage(parsed, message);
            } catch (e) {
              if (xhr.responseText === "-1" || xhr.responseText === "0") {
                message = s("sessionExpiredSettings", "Session expired. Please reload the page.");
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
