/**
 * クラシックエディタ用JavaScript
 */
(function ($) {
  "use strict";

  let currentHistory = [];
  let currentFeaturedText = "";
  let currentFeaturedPrompt = "";
  let currentImageSuggestions = [];

  $(document).ready(function () {
    // 履歴を読み込み
    loadHistory();

    // 調査ボタン
    $("#picot-ai-seo-writer-research-btn").on("click", performResearch);

    // 画像挿入ポイント探索ボタン
    $("#picot-ai-seo-writer-suggest-images-btn").on("click", suggestImages);

    // マーカークリアボタン（動的に追加されるため親要素に委譲）
    $(document).on("click", "#picot-ai-seo-writer-clear-markers-btn", function() {
        let content = "";
        if (typeof tinyMCE !== "undefined" && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
            content = tinyMCE.activeEditor.getContent();
        } else {
            content = $("#content").val();
        }
        const markerRegex = /<!-- PICOT_SEO_WRITING_MARKER:.*? -->\n?/g;
        content = content.replace(markerRegex, "");
        if (typeof tinyMCE !== "undefined" && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
            tinyMCE.activeEditor.setContent(content);
        } else {
            $("#content").val(content);
        }
        currentImageSuggestions = [];
        currentFeaturedText = "";
        currentFeaturedPrompt = "";
        $("#picot-ai-seo-writer-image-suggestions").empty();
        showMessage("マーカーと提案をクリアしました", "info");
    });
  });

  /**
   * 履歴を読み込み
   */
  function loadHistory() {
    $.ajax({
      url: picot_seo_writing_admin.rest_url + "/research/history",
      method: "GET",
      data: { post_id: picot_seo_writing_admin.post_id },
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", picot_seo_writing_admin.nonce);
      },
      success: function (response) {
        if (response.success && response.data.history) {
          currentHistory = response.data.history;
          renderHistory();
        }
      },
    });
  }

  /**
   * 履歴を表示
   */
  function renderHistory() {
    const $container = $("#picot-ai-seo-writer-history");
    $container.empty();

    if (currentHistory.length === 0) {
      $container.html(
        "<p>" +
          escapeHtml(
            picot_seo_writing_admin.strings.researchHistoryEmpty || "調査履歴がありません",
          ) +
          "</p>",
      );
      return;
    }

    currentHistory.forEach(function (item) {
      const $item = $('<div class="history-item"></div>');

      $item.append(
        '<div class="keyword">' + escapeHtml(item.target_keyword) + "</div>",
      );
      $item.append('<div class="date">' + item.created_at + "</div>");

      const urlsJa = item.locale_urls_ja ? item.locale_urls_ja.length : 0;
      const urlsEn = item.locale_urls_en ? item.locale_urls_en.length : 0;
      $item.append(
        '<div class="urls">日本語: ' +
          urlsJa +
          "件 / 英語: " +
          urlsEn +
          "件</div>",
      );

      const $actions = $('<div class="actions" style="display:flex; flex-direction:column; gap:5px;"></div>');
      $actions.append(
        $(
          '<button type="button" class="button widefat">' +
            escapeHtml(picot_seo_writing_admin.strings.generateTitle) +
            "</button>",
        ).on("click", function () {
          generateTitle(item.id);
        }),
      );
      $actions.append(
        $(
          '<button type="button" class="button widefat">' +
            escapeHtml(picot_seo_writing_admin.strings.generateArticle) +
            "</button>",
        ).on("click", function () {
          generateArticle(item.id);
        }),
      );
      $actions.append(
        $(
          '<button type="button" class="button widefat">' +
            escapeHtml(picot_seo_writing_admin.strings.checkReferenceUrls || "参照URLを確認") +
            "</button>",
        ).on("click", function () {
          showUrlsModal(item);
        }),
      );
      $item.append($actions);

      $container.append($item);
    });
  }

  /**
   * 調査を実行
   */
  function performResearch() {
    const keyword = $("#picot-ai-seo-writer-keyword").val().trim();

    if (!keyword) {
      showMessage(
        picot_seo_writing_admin.strings.enterKeyword ||
          "ターゲットワードを入力してください",
        "error",
      );
      return;
    }

    showLoading(true);
    showMessage("", "");

    $.ajax({
      url: picot_seo_writing_admin.rest_url + "/research",
      method: "POST",
      data: {
        keyword: keyword,
        post_id: picot_seo_writing_admin.post_id,
      },
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", picot_seo_writing_admin.nonce);
      },
      success: function (response) {
        if (response.success) {
          showMessage(
            picot_seo_writing_admin.strings.researchCompleted || "調査が完了しました",
            "success",
          );
          loadHistory();
          $("#picot-ai-seo-writer-keyword").val("");
        }
      },
      error: function (xhr) {
        const message =
          xhr.responseJSON && xhr.responseJSON.message
            ? xhr.responseJSON.message
            : picot_seo_writing_admin.strings.researchFailed || "調査に失敗しました";
        showMessage(message, "error");
      },
      complete: function () {
        showLoading(false);
      },
    });
  }

  /**
   * タイトルと見出しを生成
   */
  function generateTitle(researchId) {
    const additionalNotes = $("#picot-ai-seo-writer-additional-notes").val().trim();

    showLoading(true);
    showMessage(picot_seo_writing_admin.strings.generating || "生成中...", "info");

    $.ajax({
      url: picot_seo_writing_admin.rest_url + "/generate-title",
      method: "POST",
      data: {
        research_id: researchId,
        additional_notes: additionalNotes,
      },
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", picot_seo_writing_admin.nonce);
      },
      success: function (response) {
        if (response.success && response.data) {
          let headingsHtml = "";
          if (response.data.headings) {
            response.data.headings.forEach(function (heading) {
              headingsHtml += "<h" + heading.level + ">" + escapeHtml(heading.text) + "</h" + heading.level + ">\n";
            });
          }

          // #titleにセット
          if ($("#title").length) {
            $("#title").val(response.data.title);
            $("#title-prompt-text").hide();
          }

          // エディタにセット
          if (headingsHtml) {
            if (typeof tinyMCE !== "undefined" && tinyMCE.activeEditor) {
                // エディタが空かどうか判定して置換するか挿入するか
                const currentContent = tinyMCE.activeEditor.getContent({format: 'text'}).trim();
                if (!currentContent) {
                    tinyMCE.activeEditor.setContent(headingsHtml);
                } else {
                    tinyMCE.activeEditor.insertContent(headingsHtml);
                }
            } else {
                const $editor = $("#content");
                const currentContent = $editor.val().trim();
                if (!currentContent) {
                    $editor.val(headingsHtml);
                } else {
                    // append
                    $editor.val($editor.val() + "\n" + headingsHtml);
                }
            }
          }

          showMessage((picot_seo_writing_admin.strings.titleLabel || "タイトル: ") + response.data.title + "\nエディタに見出しを展開しました。自由に編集してください。", "success");
        }
      },
      error: function (xhr) {
        const message =
          xhr.responseJSON && xhr.responseJSON.message
            ? xhr.responseJSON.message
            : picot_seo_writing_admin.strings.titleGenerationFailed ||
              "タイトル生成に失敗しました";
        showMessage(message, "error");
      },
      complete: function () {
        showLoading(false);
      },
    });
  }

  /**
   * 記事を生成
   */
  function generateArticle(researchId) {
    const additionalNotes = $("#picot-ai-seo-writer-additional-notes").val().trim();

    // 現在のエディタ内容を取得
    let currentContent = "";
    if (typeof tinyMCE !== "undefined" && tinyMCE.activeEditor) {
      currentContent = tinyMCE.activeEditor.getContent();
    } else {
      currentContent = $("#content").val();
    }

    showLoading(true);
    showMessage(picot_seo_writing_admin.strings.generating || "生成中...", "info");

    $.ajax({
      url: picot_seo_writing_admin.rest_url + "/generate-article",
      method: "POST",
      data: {
        research_id: researchId,
        additional_notes: additionalNotes,
        current_content: currentContent,
      },
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", picot_seo_writing_admin.nonce);
      },
      success: function (response) {
        if (response.success && response.data.content) {
          // TinyMCEエディタ全体を置換
          if (typeof tinyMCE !== "undefined" && tinyMCE.activeEditor) {
            tinyMCE.activeEditor.setContent(response.data.content);
          } else {
            // テキストエディタの場合
            const $editor = $("#content");
            $editor.val(response.data.content);
          }

          showMessage(
            picot_seo_writing_admin.strings.articleInserted || "記事を挿入しました",
            "success",
          );
        }
      },
      error: function (xhr) {
        const message =
          xhr.responseJSON && xhr.responseJSON.message
            ? xhr.responseJSON.message
            : picot_seo_writing_admin.strings.articleGenerationFailed ||
              "記事生成に失敗しました";
        showMessage(message, "error");
      },
      complete: function () {
        showLoading(false);
      },
    });
  }

  /**
   * 画像挿入ポイントを提案
   */
  function suggestImages() {
    let content = "";

    // エディタから内容を取得
    if (typeof tinyMCE !== "undefined" && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
      content = tinyMCE.activeEditor.getContent();
    } else {
      content = $("#content").val();
    }

    // 既存のマーカーを削除
    const markerRegex = /<!-- PICOT_SEO_WRITING_MARKER:.*? -->\n?/g;
    content = content.replace(markerRegex, "");

    // 削除した内容をエディタに戻す（一時的に）
    if (typeof tinyMCE !== "undefined" && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
      tinyMCE.activeEditor.setContent(content);
    } else {
      $("#content").val(content);
    }

    if (!content) {
      showMessage(
        picot_seo_writing_admin.strings.enterContent || "記事内容を入力してください",
        "error",
      );
      return;
    }

    showLoading(true);
    showMessage("", "");
    $("#picot-ai-seo-writer-image-suggestions").empty();

    $.ajax({
      url: picot_seo_writing_admin.rest_url + "/suggest-images",
      method: "POST",
      data: { content: content },
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", picot_seo_writing_admin.nonce);
      },
      success: function (response) {
        if (response.success && response.data) {
          const suggestions = response.data.suggestions || [];
          const featured = response.data.featured_text || "";
          currentFeaturedText = featured;
          currentFeaturedPrompt = response.data.featured_prompt || "";
          currentImageSuggestions = suggestions;

          if (suggestions.length > 0) {
            autoEmbedMarkersClassic(suggestions);
            renderImageSuggestions(suggestions, featured);
            showMessage("画像挿入ポイントを提案し、エディタ内に配置マーカーを挿入しました。", "success");
          } else {
            showMessage("画像挿入ポイントが見つかりませんでした。", "info");
          }
        }
      },
      error: function (xhr) {
        const message =
          xhr.responseJSON && xhr.responseJSON.message
            ? xhr.responseJSON.message
            : picot_seo_writing_admin.strings.suggestionFailed || "画像提案に失敗しました";
        showMessage(message, "error");
      },
      complete: function () {
        showLoading(false);
      },
    });
  }

  /**
   * クラシックエディタにマーカーを埋め込む
   */
  function autoEmbedMarkersClassic(suggestions) {
    let content = "";
    const isTinyMCE = typeof tinyMCE !== "undefined" && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden();

    if (isTinyMCE) {
      content = tinyMCE.activeEditor.getContent();
    } else {
      content = $("#content").val();
    }

    suggestions.forEach(function (suggestion, index) {
      const marker = "<!-- PICOT_SEO_WRITING_MARKER:" + index + ": " + suggestion.prompt + " -->";
      if (content.indexOf("PICOT_SEO_WRITING_MARKER:" + index + ":") !== -1) return;

      const searchText = suggestion.location;
      if (!searchText) return;

      // 簡易検索と置換
      const pos = content.indexOf(searchText);
      if (pos !== -1) {
        const insertPos = pos + searchText.length;
        content = content.slice(0, insertPos) + "\n" + marker + "\n" + content.slice(insertPos);
      }
    });

    if (isTinyMCE) {
      tinyMCE.activeEditor.setContent(content);
    } else {
      $("#content").val(content);
    }
  }

  /**
   * 画像提案UIを表示
   */
  function renderImageSuggestions(suggestions, featuredText) {
    const $container = $("#picot-ai-seo-writer-image-suggestions");
    $container.empty();

    // クリアボタンを追加
    $container.append('<div style="margin-bottom:10px;"><button type="button" id="picot-ai-seo-writer-clear-markers-btn" class="button button-small" style="width:100%;">提案とマーカーをクリア</button></div>');

    if (featuredText) {
      const $fItem = $('<div class="history-item" style="background:#fff3cd; border-color:#ffc107;"></div>');
      $fItem.append('<div style="font-weight:bold; margin-bottom:5px;">⭐ アイキャッチ画像</div>');
      $fItem.append('<div style="font-size:11px; margin-bottom:10px;">' + escapeHtml(featuredText) + '</div>');
      
      const $fBtn = $('<button type="button" class="button button-primary">生成してアイキャッチに設定</button>');
      $fBtn.on("click", function() {
        generateAndPlaceImageClassic({
          prompt: currentFeaturedPrompt || ('Professional blog featured image with text "' + featuredText + '" in center, clean background'),
          description: 'Featured Image'
        }, -1, true);
      });
      $fItem.append($fBtn);
      $container.append($fItem);
    }

    suggestions.forEach(function (suggestion, index) {
      const $item = $('<div class="history-item"></div>');
      $item.append('<div style="font-weight:bold; font-size:11px;">📍 ' + escapeHtml(suggestion.location) + '</div>');
      $item.append('<div style="font-size:11px; margin-bottom:8px; color:#666;">' + escapeHtml(suggestion.description) + '</div>');
      
      const $btn = $('<button type="button" class="button">生成して配置</button>');
      $btn.on("click", function() {
        generateAndPlaceImageClassic(suggestion, index);
      });
      $item.append($btn);
      $container.append($item);
    });
  }

  /**
   * 画像を生成してエディタに配置（またはアイキャッチ設定）
   */
  function generateAndPlaceImageClassic(suggestion, index, isFeatured) {
    showLoading(true);
    showMessage("画像を生成中...", "info");

    $.ajax({
      url: picot_seo_writing_admin.rest_url + "/generate-image",
      method: "POST",
      data: {
        prompt: suggestion.prompt,
        post_id: picot_seo_writing_admin.post_id
      },
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", picot_seo_writing_admin.nonce);
      },
      success: function (response) {
        if (response.success && response.url) {
          if (isFeatured) {
            // アイキャッチに設定
            $("#_thumbnail_id").val(response.attachment_id);
            if (window.setPostThumbnail) {
              window.setPostThumbnail(response.attachment_id);
            }
            currentFeaturedText = "";
            currentFeaturedPrompt = "";
            renderImageSuggestions(currentImageSuggestions, "");
            showMessage("アイキャッチ画像を設定しました", "success");
          } else {
            const markerSearch = "PICOT_SEO_WRITING_MARKER:" + index + ":";
            const imageHtml = '<div style="text-align:center;"><img src="' + response.url + '" alt="' + escapeHtml(suggestion.description) + '" /><br /><small>' + escapeHtml(suggestion.description) + '</small></div>';
            
            const isTinyMCE = typeof tinyMCE !== "undefined" && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden();
            if (isTinyMCE) {
              let content = tinyMCE.activeEditor.getContent();
              // マーカーを検索して置換（プロンプトが含まれるため、indexOf + サブストリングで処理）
              const startPos = content.indexOf("<!-- " + markerSearch);
              if (startPos !== -1) {
                const endPos = content.indexOf(" -->", startPos);
                if (endPos !== -1) {
                  content = content.slice(0, startPos) + imageHtml + content.slice(endPos + 4);
                  tinyMCE.activeEditor.setContent(content);
                }
              }
            } else {
              let content = $("#content").val();
              const startPos = content.indexOf("<!-- " + markerSearch);
              if (startPos !== -1) {
                const endPos = content.indexOf(" -->", startPos);
                if (endPos !== -1) {
                  content = content.slice(0, startPos) + imageHtml + content.slice(endPos + 4);
                  $("#content").val(content);
                }
              }
            }
            showMessage("画像を挿入しました", "success");
          }
        }
      },
      error: function (xhr) {
        showMessage("画像生成に失敗しました", "error");
      },
      complete: function () {
        showLoading(false);
      }
    });
  }

  /**
   * ローディング表示
   */
  function showLoading(show, message) {
    if (!window.PicotSeoWritingOverlay) {
      return;
    }

    if (show) {
      const strings = picot_seo_writing_admin.strings || {};
      window.PicotSeoWritingOverlay.show(
        message || strings.writingInProgress || strings.generating || "Geminiが執筆中...",
        strings.overlaySubmessage || "これには数十秒かかる場合があります。"
      );
      return;
    }

    window.PicotSeoWritingOverlay.hide();
  }

  /**
   * メッセージ表示
   */
  function showMessage(message, type) {
    const $message = $("#picot-ai-seo-writer-message");
    $message.html(message);
    $message.removeClass("success error info");

    if (type) {
      $message.addClass(type);
    }
  }

  /**
   * 参照URLモーダルを表示
   */
  function showUrlsModal(item) {
    // 既存のモーダルがあれば削除
    $("#picot-ai-seo-writer-url-modal").remove();

    let html = '<div id="picot-ai-seo-writer-url-modal" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:999999; display:flex; justify-content:center; align-items:center;">';
    html += '<div style="background:#fff; padding:20px; border-radius:4px; width:600px; max-width:90%; max-height:90vh; overflow-y:auto; position:relative; box-shadow: 0 5px 15px rgba(0,0,0,0.3);">';
    html += '<h2 style="margin-top:0; font-size:16px; border-bottom:1px solid #ccc; padding-bottom:10px;">' + escapeHtml(item.target_keyword) + ' - 参照URL</h2>';
    
    html += '<h3 style="font-size:15px; margin: 15px 0 10px;">日本国内の検索順位 (上位10件)</h3><ul style="list-style:disc; margin-left:20px; margin-bottom: 20px;">';
    (item.locale_urls_ja || []).forEach(urlInfo => {
      html += '<li style="margin-bottom:5px;"><a href="' + escapeHtml(urlInfo.url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(urlInfo.title || urlInfo.url) + '</a></li>';
    });
    html += '</ul>';

    html += '<h3 style="font-size:15px; margin: 15px 0 10px;">英語圏の検索順位 (上位5件)</h3><ul style="list-style:disc; margin-left:20px; margin-bottom: 20px;">';
    (item.locale_urls_en || []).forEach(urlInfo => {
      html += '<li style="margin-bottom:5px;"><a href="' + escapeHtml(urlInfo.url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(urlInfo.title || urlInfo.url) + '</a></li>';
    });
    html += '</ul>';
    
    html += '<div style="text-align:right; margin-top:20px; border-top:1px solid #ccc; padding-top:15px;">';
    html += '<button type="button" class="button" id="picot-ai-seo-writer-close-modal">閉じる</button>';
    html += '</div>';
    html += '</div></div>';
    
    $("body").append(html);
    
    $("#picot-ai-seo-writer-close-modal").on("click", function() {
      $("#picot-ai-seo-writer-url-modal").remove();
    });
  }

  /**
   * HTMLエスケープ
   */
  function escapeHtml(text) {
    const map = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    };
    return text.replace(/[&<>"']/g, function (m) {
      return map[m];
    });
  }
})(jQuery);
