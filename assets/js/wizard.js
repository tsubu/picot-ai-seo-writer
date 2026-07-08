/**
 * Picot AI SEO Writer Setup Wizard JS - Gemini Edition
 */
jQuery(document).ready(function($) {
    const strings = (picot_seo_writing_wizard && picot_seo_writing_wizard.strings) || {};

    function s(key, fallback) {
        return strings[key] || fallback;
    }

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

        if (index === 0) {
            $prevBtn.hide();
        } else {
            $prevBtn.show();
        }

        if (index === $screens.length - 1) {
            $nextBtn.text(s('submit', 'Save settings and finish'));
        } else {
            $nextBtn.text(s('next', 'Next'));
        }
    }

    $prevBtn.on('click', function() {
        if (currentStep > 0) {
            currentStep--;
            updateStep(currentStep);
        }
    });

    let modelDescriptions = picot_seo_writing_wizard.model_descriptions || {};

    function updateDescription($select, $descDiv) {
        const val = $select.val();
        const desc = modelDescriptions[val] || '';
        $descDiv.text(desc);
    }

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

    function fetchGeminiModels($btn) {
        const deferred = $.Deferred();
        const originalText = $btn ? $btn.text() : null;

        if ($btn) {
            $btn.prop('disabled', true).text(s('fetchingModels', 'Fetching model list...'));
        }

        $.ajax({
            url: picot_seo_writing_wizard.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'picot_seo_writing_fetch_gemini_models',
                nonce: picot_seo_writing_wizard.nonce
            }
        }).done(function(response) {
            if (!response || !response.success) {
                deferred.reject({
                    message: getErrorMessage(response, s('errorFetching', 'Failed to fetch model list.'))
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
            let message = s('communicationErrorPlain', 'A communication error occurred.');
            if (xhr && xhr.responseText) {
                try {
                    const parsed = JSON.parse(xhr.responseText);
                    message = getErrorMessage(parsed, message);
                } catch (e) {
                    if (xhr.responseText === '-1' || xhr.responseText === '0') {
                        message = s('sessionExpiredWizard', 'Your session has expired. Reload the page and try again.');
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

    $(document).on('click', '#picot-wizard-fetch-models', function() {
        fetchGeminiModels($(this)).fail(function(error) {
            alert((error && error.message) || s('errorFetching', 'Failed to fetch model list.'));
        });
    });

    $nextBtn.on('click', function() {
        if (currentStep < $screens.length - 1) {
            const $currentScreen = $screens.eq(currentStep);

            if (getScreenStepId($currentScreen) === 'ai_setup') {
                $nextBtn.prop('disabled', true).text(s('testing', 'Testing connection...'));

                $.ajax({
                    url: picot_seo_writing_wizard.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'picot_seo_writing_test_connection',
                        nonce: picot_seo_writing_wizard.nonce
                    }
                }).done(function(response) {
                    if (!response || !response.success) {
                        alert(getErrorMessage(response, s('geminiConnectionFailed', 'Failed to connect to the Google Gemini connector.')));
                        return;
                    }

                    fetchGeminiModels(null).done(function() {
                        currentStep++;
                        updateStep(currentStep);
                    }).fail(function(error) {
                        alert((error && error.message) || s('modelFetchFailed', 'Failed to fetch models.'));
                    });
                }).fail(function() {
                    alert(s('geminiConnectionTestFailed', 'Google Gemini connector connection test failed.'));
                }).always(function() {
                    $nextBtn.prop('disabled', false).text(
                        currentStep === $screens.length - 1
                            ? s('submit', 'Save settings and finish')
                            : s('next', 'Next')
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

    $(document).on('focus', '.picot-hover-show', function() {
        $(this).attr('type', 'text');
    }).on('blur', '.picot-hover-show', function() {
        $(this).attr('type', 'password');
    });

    if ($screens.length > 0) {
        updateStep(0);
        updateDescription($('#picot_seo_writing_text_model'), $('#picot_seo_writing_text_model_description'));
        updateDescription($('#picot_seo_writing_image_model'), $('#picot_seo_writing_image_model_description'));
    }
});
