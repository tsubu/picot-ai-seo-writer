/**
 * Picot AI SEO Writer Setup Wizard JS - Gemini Edition
 */
jQuery(document).ready(function($) {
    
    // ステップ制御の初期化
    let currentStep = 0;
    const $screens = $('.picot-wizard-screen');
    const $indicators = $('.picot-wizard-step-indicator');
    const $nextBtn = $('#picot-next-btn');
    const $prevBtn = $('#picot-prev-btn');
    const $form = $('#picot-wizard-form');

    function updateStep(index) {
        $screens.removeClass('active').hide().eq(index).fadeIn(300).addClass('active');
        $indicators.removeClass('active');
        for (let i = 0; i <= index; i++) {
            $indicators.eq(i).addClass('active');
        }
        
        // 戻るボタンの表示制御
        if (index === 0) {
            $prevBtn.hide();
        } else {
            $prevBtn.show();
        }
        
        // 次へ/送信ボタンの文言切り替え
        if (index === $screens.length - 1) {
            $nextBtn.text(picot_seo_writing_wizard.strings.submit || '設定を保存して完了する');
        } else {
            $nextBtn.text(picot_seo_writing_wizard.strings.next || '次へ進む');
        }
    }

    // 戻るボタンのクリック
    $prevBtn.on('click', function() {
        if (currentStep > 0) {
            currentStep--;
            updateStep(currentStep);
        }
    });

    // モデル説明文の保持用
    let modelDescriptions = picot_seo_writing_wizard.model_descriptions || {};

    function updateDescription($select, $descDiv) {
        const val = $select.val();
        const desc = modelDescriptions[val] || '';
        $descDiv.text(desc);
    }

    // モデル選択変更時のイベント
    $(document).on('change', '#picot_seo_writing_text_model', function() {
        updateDescription($(this), $('#picot_seo_writing_text_model_description'));
    });
    $(document).on('change', '#picot_seo_writing_image_model', function() {
        updateDescription($(this), $('#picot_seo_writing_image_model_description'));
    });

    function getScreenStepId($screen) {
        return $screen.attr('data-step-id') || $screen.data('stepId') || '';
    }

    function getErrorMessage(response, fallback) {
        if (response && response.data) {
            if (typeof response.data.message === 'string' && response.data.message) {
                return response.data.message;
            }
            if (typeof response.data === 'string' && response.data) {
                return response.data;
            }
        }
        return fallback;
    }

    // Geminiモデル取得関数
    function fetchGeminiModels(apiKey, $btn) {
        const deferred = $.Deferred();

        if (!apiKey) {
            deferred.reject({ message: 'APIキーを入力してください。' });
            return deferred.promise();
        }

        const originalText = $btn ? $btn.text() : null;
        if ($btn) {
            $btn.prop('disabled', true).text(picot_seo_writing_wizard.strings.fetchingModels || '取得中...');
        }

        $.ajax({
            url: picot_seo_writing_wizard.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'picot_seo_writing_fetch_gemini_models',
                nonce: picot_seo_writing_wizard.nonce,
                api_key: apiKey
            }
        }).done(function(response) {
            if (!response || !response.success) {
                deferred.reject({
                    message: getErrorMessage(response, picot_seo_writing_wizard.strings.errorFetching || 'モデル一覧の取得に失敗しました。')
                });
                return;
            }

            if (response.data.descriptions) {
                modelDescriptions = response.data.descriptions;
            }

            const $modelSelect = $('#picot_seo_writing_text_model');
            const currentValue = $modelSelect.val();
            $modelSelect.empty();
            $.each(response.data.models || {}, function(id, label) {
                const selected = (id === currentValue) ? 'selected' : '';
                $modelSelect.append(`<option value="${id}" ${selected}>${label}</option>`);
            });
            updateDescription($modelSelect, $('#picot_seo_writing_text_model_description'));

            const $imgModelSelect = $('#picot_seo_writing_image_model');
            const currentImgValue = $imgModelSelect.val();
            if ($imgModelSelect.length && response.data.image_models) {
                $imgModelSelect.empty();
                $.each(response.data.image_models, function(id, label) {
                    const selected = (id === currentImgValue) ? 'selected' : '';
                    $imgModelSelect.append(`<option value="${id}" ${selected}>${label}</option>`);
                });
                updateDescription($imgModelSelect, $('#picot_seo_writing_image_model_description'));
            }

            deferred.resolve(response);
        }).fail(function(xhr) {
            let message = '通信エラーが発生しました。';
            if (xhr && xhr.responseText) {
                try {
                    const parsed = JSON.parse(xhr.responseText);
                    message = getErrorMessage(parsed, message);
                } catch (e) {
                    if (xhr.responseText === '-1' || xhr.responseText === '0') {
                        message = 'セッションが切れています。ページを再読み込みして再度お試しください。';
                    }
                }
            }
            deferred.reject({ message: message });
        }).always(function() {
            if ($btn) {
                $btn.prop('disabled', false).text(originalText);
            }
        });

        return deferred.promise();
    }

    // APIキー入力時に自動取得 (blurイベント)
    $('#picot_seo_writing_gemini_api_key').on('blur', function() {
        fetchGeminiModels($(this).val());
    });

    // 次へボタンのクリック
    $nextBtn.on('click', function() {
        if (currentStep < $screens.length - 1) {
            const $currentScreen = $screens.eq(currentStep);
            
            // Step 1 (API Key) から Step 2 へ進む際の処理
            if (getScreenStepId($currentScreen) === 'api_key') {
                const apiKey = $('#picot_seo_writing_gemini_api_key').val();
                if (!apiKey) {
                    alert('APIキーを入力してください。');
                    return;
                }

                $nextBtn.prop('disabled', true).text(picot_seo_writing_wizard.strings.testing || '接続テスト中...');

                fetchGeminiModels(apiKey).done(function() {
                    currentStep++;
                    updateStep(currentStep);
                }).fail(function(error) {
                    alert((error && error.message) || 'APIキーが無効か、モデルの取得に失敗しました。');
                }).always(function() {
                    $nextBtn.prop('disabled', false).text(
                        currentStep === $screens.length - 1
                            ? (picot_seo_writing_wizard.strings.submit || '設定を保存して完了する')
                            : (picot_seo_writing_wizard.strings.next || '次へ進む')
                    );
                });
                return;
            }

            currentStep++;
            updateStep(currentStep);
        } else {
            $form.submit();
        }
    });

    // モデル再取得ボタン
    $(document).on('click', '#picot-wizard-fetch-models', function() {
        const apiKey = $('#picot_seo_writing_gemini_api_key').val();
        fetchGeminiModels(apiKey, $(this)).fail(function(error) {
            alert((error && error.message) || 'モデル一覧の取得に失敗しました。');
        });
    });

    // フォーカス時にパスワードを表示
    $(document).on('focus', '.picot-hover-show', function() {
        $(this).attr('type', 'text');
    }).on('blur', '.picot-hover-show', function() {
        $(this).attr('type', 'password');
    });

    // 初期化実行
    if ($screens.length > 0) {
        updateStep(0);
        // 初期選択モデルの説明を表示
        updateDescription($('#picot_seo_writing_text_model'), $('#picot_seo_writing_text_model_description'));
        updateDescription($('#picot_seo_writing_image_model'), $('#picot_seo_writing_image_model_description'));
    }
});
