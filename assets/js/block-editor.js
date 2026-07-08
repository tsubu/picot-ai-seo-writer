(function() {
    'use strict';

    const { registerPlugin } = wp.plugins;
    const { PluginSidebar } = wp.editPost;
    const { PanelBody, TextControl, TextareaControl, SelectControl, Button } = wp.components;
    const { useState, useEffect, createElement: el } = wp.element;

    const formatString = (template, ...args) => {
        if (!template) {
            return '';
        }
        let argIndex = 0;
        return template.replace(/%(\d+\$)?[ds]/g, (match, position) => {
            const index = position ? parseInt(position, 10) - 1 : argIndex++;
            return args[index] !== undefined ? String(args[index]) : match;
        });
    };

    const PICOT_SEO_WRITINGSidebar = () => {
        const config = window.picotSeoWriting || window.picot_seo_writing_admin || {};
        const strings = config.strings || {};
        const t = (key, fallback = '') => strings[key] || fallback;
        const writingStyleOptions = config.writingStyleOptions || [];
        const imageStyleOptions = config.imageStyleOptions || [];

        const [keyword, setKeyword]             = useState(config.lastKeyword || config.target_keyword || '');
        const [additionalNotes, setAdditionalNotes] = useState(config.lastNotes || config.additional_notes || '');
        const [writingStyle, setWritingStyle]   = useState(config.currentStyle || 'casual');
        const [imageStyle, setImageStyle]       = useState(config.currentImageStyle || 'photorealistic');
        const [loading, setLoading]             = useState(false);
        const [imgLoading, setImgLoading]       = useState(false);
        const [imgSuccess, setImgSuccess]       = useState(!!config.imageSuggestions);
        const [message, setMessage]             = useState({ text: '', type: '' });
        const [imageSuggestions, setImageSuggestions] = useState(config.imageSuggestions || null);
        const [generatingIdx, setGeneratingIdx] = useState(-1);
        const [bulkInfo, setBulkInfo]           = useState(null);
        const [completedImages, setCompletedImages] = useState([]);

        const initialSources = () => {
            const src = config.lastSources || config.sources || [];
            if (typeof src === 'string' && src) {
                try { return JSON.parse(src); } catch (e) { return []; }
            }
            return Array.isArray(src) ? src : [];
        };
        const [currentSources, setCurrentSources] = useState(initialSources);
        const [currentGenerationInfo, setCurrentGenerationInfo] = useState({
            keyword: config.lastKeyword || config.target_keyword || '',
            additionalNotes: config.lastNotes || config.additional_notes || ''
        });

        const handleKeywordChange = (val) => {
            setKeyword(val);
            wp.data.dispatch('core/editor').editPost({ meta: { picot_seo_writing_keyword: val } });
        };
        const handleNotesChange = (val) => {
            setAdditionalNotes(val);
            wp.data.dispatch('core/editor').editPost({ meta: { picot_seo_writing_notes: val } });
        };
        const handleStyleChange = (val) => {
            setWritingStyle(val);
            wp.data.dispatch('core/editor').editPost({ meta: { picot_seo_writing_style: val } });
        };
        const handleImageStyleChange = (val) => {
            setImageStyle(val);
            wp.data.dispatch('core/editor').editPost({ meta: { picot_seo_writing_image_style: val } });
        };

        useEffect(() => {
            const isBusy = loading || imgLoading || generatingIdx !== -1 || bulkInfo !== null;
            if (!isBusy || !window.PicotSeoWritingOverlay) {
                if (window.PicotSeoWritingOverlay) {
                    window.PicotSeoWritingOverlay.hide();
                }
                return undefined;
            }

            let msg = t('generatingArticle', 'AI is generating the article...');
            if (imgLoading) {
                msg = t('analyzingImagePrompts', 'Analyzing image prompts...');
            } else if (bulkInfo) {
                msg = formatString(
                    t('generatingBulkImage', 'Generating image %1$d of %2$d...'),
                    bulkInfo.current,
                    bulkInfo.total
                );
            } else if (generatingIdx === -2) {
                msg = t('generatingFeaturedImage', 'Generating featured image...');
            } else if (generatingIdx >= 0) {
                msg = formatString(t('generatingImageNumber', 'Generating image %d...'), generatingIdx + 1);
            }

            window.PicotSeoWritingOverlay.show(msg, t('overlaySubmessage', 'This may take several tens of seconds.'));

            return () => {
                if (window.PicotSeoWritingOverlay) {
                    window.PicotSeoWritingOverlay.hide();
                }
            };
        }, [loading, imgLoading, generatingIdx, bulkInfo, strings]);

        const generateArticle = () => {
            if (!keyword.trim()) {
                setMessage({ text: t('enterKeyword', 'Please enter a target keyword'), type: 'error' });
                return;
            }
            setLoading(true);
            setMessage({ text: '', type: '' });
            setCurrentSources([]);
            setImageSuggestions(null);
            setImgSuccess(false);
            setCompletedImages([]);
            setBulkInfo(null);

            wp.apiFetch({
                url: config.restUrl + config.namespace + '/generate-article-direct',
                method: 'POST',
                headers: { 'X-WP-Nonce': config.nonce },
                data: { keyword, additional_notes: additionalNotes, writing_style: writingStyle, post_id: config.postId }
            })
            .then(data => {
                const r = data.data || data;
                if (r.success && r.article_content) {
                    const edits = { title: r.title || keyword };
                    if (r.excerpt) edits.excerpt = r.excerpt;
                    wp.data.dispatch('core/editor').editPost(edits);
                    if (wp.blocks && wp.blocks.rawHandler && wp.data.dispatch('core/block-editor')) {
                        const blocks = wp.blocks.rawHandler({ HTML: r.article_content });
                        wp.data.dispatch('core/block-editor').resetBlocks(blocks);
                    } else {
                        wp.data.dispatch('core/editor').editPost({ content: r.article_content });
                    }
                    wp.apiFetch({
                        url: config.restUrl + config.namespace + '/save-meta',
                        method: 'POST',
                        headers: { 'X-WP-Nonce': config.nonce },
                        data: { post_id: config.postId, keyword, notes: additionalNotes, sources: JSON.stringify(r.sources || []) }
                    }).catch(e => console.error('PICOT SEO: Meta save error:', e));
                    setCurrentSources(r.sources || []);
                    setCurrentGenerationInfo({ keyword, additionalNotes });
                    setMessage({ text: t('articleGenerated', 'Article generated!'), type: 'success' });
                } else {
                    setMessage({ text: r.error || t('error', 'An error occurred'), type: 'error' });
                }
            })
            .catch(err => {
                const msg = err.message || (err.data && err.data.message) || t('unknownError', 'Unknown error');
                setMessage({ text: t('errorPrefix', 'Error: ') + msg, type: 'error' });
            })
            .finally(() => setLoading(false));
        };

        const generateImagePrompts = () => {
            const content = wp.data.select('core/editor').getEditedPostAttribute('content');
            if (!content || !content.trim()) {
                setMessage({ text: t('generateArticleFirst', 'Generate an article first'), type: 'error' });
                return;
            }
            setImgLoading(true);
            setImgSuccess(false);
            setImageSuggestions(null);
            setMessage({ text: '', type: '' });
            setCompletedImages([]);
            setBulkInfo(null);

            wp.apiFetch({
                url: config.restUrl + config.namespace + '/insert-image-prompts',
                method: 'POST',
                headers: { 'X-WP-Nonce': config.nonce },
                data: { content, post_id: config.postId }
            })
            .then(data => {
                if (data && data.code && data.message) {
                    setMessage({ text: data.message, type: 'error' });
                    return;
                }
                if (data && data.success && data.data) {
                    setImgSuccess(true);
                    setImageSuggestions(data.data);
                    setMessage({ text: t('imageSuggestionsReady', 'Image suggestions are ready. Generate them from the list below.'), type: 'success' });
                } else {
                    setMessage({ text: (data && data.message) || t('imagePromptInsertFailed', 'Failed to insert image prompts'), type: 'error' });
                }
            })
            .catch(err => {
                const msg = (err && err.message) ? err.message : JSON.stringify(err);
                setMessage({ text: t('communicationError', 'Communication error: ') + msg, type: 'error' });
            })
            .finally(() => setImgLoading(false));
        };

        const generateSingleImage = async (prompt, description, location, isFeatured, idx) => {
            setGeneratingIdx(isFeatured ? -2 : idx);
            try {
                const result = await wp.apiFetch({
                    url: config.restUrl + config.namespace + '/generate-image',
                    method: 'POST',
                    headers: { 'X-WP-Nonce': config.nonce },
                    data: { prompt, post_id: config.postId, image_style: imageStyle }
                });
                const r = result.data || result;
                if (!r.url || !r.attachment_id) {
                    throw new Error(r.message || t('noImageDataReturned', 'No image data was returned'));
                }

                if (isFeatured) {
                    wp.data.dispatch('core/editor').editPost({ featured_media: r.attachment_id });
                    const imageBlock = wp.blocks.createBlock('core/image', {
                        id: r.attachment_id,
                        url: r.url,
                        alt: description || '',
                        caption: '',
                    });
                    wp.data.dispatch('core/block-editor').insertBlocks(imageBlock, 0);
                    setMessage({ text: t('featuredImageSet', 'Featured image set and inserted!'), type: 'success' });
                } else {
                    insertImageBlock(r.attachment_id, r.url, description, location);
                    setMessage({ text: t('imageInsertedIntoPost', 'Image inserted into the post!'), type: 'success' });
                }
                setCompletedImages(prev => [...prev, isFeatured ? -2 : idx]);
            } catch (err) {
                const msg = (err && err.message) ? err.message : JSON.stringify(err);
                setMessage({ text: t('imageGenerationErrorPrefix', 'Image generation error: ') + msg, type: 'error' });
            } finally {
                setGeneratingIdx(-1);
            }
        };

        const getAllBlocks = (blocks = []) => {
            let flattened = [];
            blocks.forEach(block => {
                flattened.push(block);
                if (block.innerBlocks && block.innerBlocks.length > 0) {
                    flattened = flattened.concat(getAllBlocks(block.innerBlocks));
                }
            });
            return flattened;
        };

        const insertImageBlock = (attachmentId, url, alt, locationText) => {
            const imageBlock = wp.blocks.createBlock('core/image', {
                id: attachmentId,
                url,
                alt: alt || '',
                caption: '',
            });

            const normalize = (txt) => {
                return (txt || '')
                    .replace(/<[^>]*>/g, '')
                    .replace(/[ \s　.,!?:;()[\]{}<>"'|\\/_~`\-+*=&%$#@^!！。、？：；（）［］｛｝＜＞”’｜￥＿〜｀－＋＊＝＆％＄＃＠＾]/g, '')
                    .toLowerCase();
            };

            const rootBlocks = wp.data.select('core/block-editor').getBlocks();
            let targetClientId = null;

            if (locationText && locationText.trim()) {
                const searchNorm = normalize(locationText);
                if (searchNorm) {
                    const allBlocks = getAllBlocks(rootBlocks);
                    const searchPart = searchNorm.slice(0, 50);

                    for (let block of allBlocks) {
                        const attrs = block.attributes || {};
                        const blockTextNorm = normalize(attrs.content || attrs.value || '');

                        if (blockTextNorm && (blockTextNorm.includes(searchPart) || searchPart.includes(blockTextNorm))) {
                            targetClientId = block.clientId;
                            break;
                        }
                    }
                }
            }

            if (targetClientId) {
                const editorSelect = wp.data.select('core/block-editor');
                let topLevelId = targetClientId;
                let currentParentId = editorSelect.getBlockRootClientId(topLevelId);

                while (currentParentId) {
                    topLevelId = currentParentId;
                    currentParentId = editorSelect.getBlockRootClientId(topLevelId);
                }

                const index = editorSelect.getBlockIndex(topLevelId);
                wp.data.dispatch('core/block-editor').insertBlocks([imageBlock], index + 1, undefined, true);

                setTimeout(() => {
                    const element = document.querySelector(`[data-block="${imageBlock.clientId}"]`);
                    if (element) {
                        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }, 500);
            } else {
                const allRootBlocks = wp.data.select('core/block-editor').getBlocks();
                wp.data.dispatch('core/block-editor').insertBlocks([imageBlock], allRootBlocks.length, undefined, true);
            }
        };

        const generateAllImages = async () => {
            if (!imageSuggestions) return;
            setMessage({ text: t('generatingAllImages', 'Generating all images... (this may take a while)'), type: 'success' });

            const allItems = [
                { prompt: imageSuggestions.featured_prompt, description: imageSuggestions.featured_text, location: '', isFeatured: true, idx: -2 },
                ...(imageSuggestions.suggestions || []).map((s, i) => ({ ...s, isFeatured: false, idx: i }))
            ];

            const pendingItems = allItems.filter(item => !completedImages.includes(item.idx));

            if (pendingItems.length === 0) {
                setMessage({ text: t('allImagesGenerated', 'All images have already been generated!'), type: 'success' });
                return;
            }

            setBulkInfo({ current: 1, total: pendingItems.length });

            for (let i = 0; i < pendingItems.length; i++) {
                const item = pendingItems[i];
                setBulkInfo({ current: i + 1, total: pendingItems.length });
                await generateSingleImage(item.prompt, item.description, item.location, item.isFeatured, item.idx);
                await new Promise(r => setTimeout(r, 1000));
            }

            setBulkInfo(null);
            setMessage({ text: t('allImagesComplete', 'All images were generated and inserted!'), type: 'success' });
        };

        const isDisabled = loading || imgLoading || generatingIdx !== -1;

        const renderImageSuggestions = () => {
            if (!imageSuggestions) return null;
            const { featured_prompt, featured_text, suggestions = [] } = imageSuggestions;

            return el('div', { style: { marginTop: '12px' } },
                el('div', {
                    style: {
                        border: '2px solid #f56e28', borderRadius: '6px',
                        padding: '10px', marginBottom: '8px', background: '#fff8f5'
                    }
                },
                    el('strong', { style: { fontSize: '12px', color: '#f56e28' } }, t('featuredImageLabel', 'Featured image')),
                    el('p', { style: { fontSize: '11px', margin: '4px 0', color: '#555' } }, featured_text),
                    !completedImages.includes(-2) ? el(Button, {
                        isSecondary: true,
                        onClick: () => generateSingleImage(featured_prompt, featured_text, '', true, -2),
                        isBusy: generatingIdx === -2,
                        disabled: isDisabled,
                        style: { width: '100%', justifyContent: 'center', marginTop: '6px', height: '32px', fontSize: '12px' }
                    }, generatingIdx === -2 ? t('generating', 'Generating...') : t('generateAndSetFeatured', 'Generate and set')) : el('div', { style: { color: '#155724', fontSize: '12px', marginTop: '6px', textAlign: 'center', fontWeight: 'bold' } }, t('generationComplete', 'Generated'))
                ),
                ...suggestions.map((s, i) =>
                    el('div', {
                        key: i,
                        style: {
                            border: '1px solid #ccd0d4', borderRadius: '6px',
                            padding: '10px', marginBottom: '8px', background: '#f9f9f9'
                        }
                    },
                        el('strong', { style: { fontSize: '11px', color: '#2271b1' } }, `📍 ${i + 1}. ${s.description || s.location}`),
                        el('p', { style: { fontSize: '10px', color: '#777', margin: '4px 0', wordBreak: 'break-all' } }, s.location),
                        !completedImages.includes(i) ? el(Button, {
                            isSecondary: true,
                            onClick: () => generateSingleImage(s.prompt, s.description, s.location, false, i),
                            isBusy: generatingIdx === i,
                            disabled: isDisabled,
                            style: { width: '100%', justifyContent: 'center', marginTop: '4px', height: '30px', fontSize: '11px' }
                        }, generatingIdx === i ? t('generating', 'Generating...') : t('generateAndInsertImage', 'Generate and insert')) : el('div', { style: { color: '#155724', fontSize: '11px', marginTop: '4px', textAlign: 'center', fontWeight: 'bold' } }, t('generationComplete', 'Generated'))
                    )
                ),
                el(Button, {
                    isPrimary: true,
                    onClick: generateAllImages,
                    disabled: isDisabled,
                    style: {
                        width: '100%', justifyContent: 'center', marginTop: '8px', height: '40px',
                        background: '#1d2327', borderColor: '#1d2327'
                    }
                }, t('generateAllImagesButton', 'Generate and insert all images'))
            );
        };

        return el(PluginSidebar, {
            name: 'picot-ai-seo-writer-sidebar',
            title: t('title', 'Picot AI SEO Writer'),
            icon: el('span', { className: 'dashicons dashicons-admin-appearance' })
        },
            el('div', { className: 'picot-sidebar-content', style: { padding: '16px' } },
                el(PanelBody, { title: t('articleGenerationSettings', 'Article generation settings'), initialOpen: true },
                    el('div', { style: { marginBottom: '20px' } },
                        el(TextControl, {
                            label: t('targetKeyword', 'Target keyword'),
                            value: keyword,
                            onChange: handleKeywordChange,
                            placeholder: t('matchKeywordPlaceholder', 'e.g. WordPress SEO'),
                            disabled: isDisabled
                        })
                    ),
                    el('div', { style: { marginBottom: '20px' } },
                        el(TextareaControl, {
                            label: t('additionalNotesOptional', 'Additional notes (optional)'),
                            value: additionalNotes,
                            onChange: handleNotesChange,
                            rows: 5,
                            placeholder: t('additionalNotesDetailedPlaceholder', 'Enter specific details or requests to include in the article'),
                            disabled: isDisabled
                        })
                    ),
                    el('div', { style: { marginTop: '16px' } },
                        el(Button, {
                            isPrimary: true,
                            onClick: generateArticle,
                            isBusy: loading,
                            disabled: isDisabled,
                            style: { width: '100%', justifyContent: 'center', height: '40px' }
                        }, loading ? t('generating', 'Generating...') : t('generateArticleButton', 'Generate article'))
                    ),
                    message.text && el('div', {
                        style: {
                            marginTop: '12px', padding: '8px', borderRadius: '4px',
                            backgroundColor: message.type === 'error' ? '#f8d7da' : '#d4edda',
                            color: message.type === 'error' ? '#721c24' : '#155724',
                            fontSize: '12px'
                        }
                    }, message.text)
                ),

                el(PanelBody, { title: t('writingStylePanel', 'Writing style'), initialOpen: false },
                    el('div', { style: { marginBottom: '20px' } },
                        el(SelectControl, {
                            label: t('writingStyleLabel', 'Writing style'),
                            value: writingStyle,
                            options: writingStyleOptions,
                            onChange: handleStyleChange,
                            disabled: isDisabled
                        })
                    ),
                    el('div', { style: { marginBottom: '10px' } },
                        el(SelectControl, {
                            label: t('imageStyleLabel', 'Image style'),
                            value: imageStyle,
                            options: imageStyleOptions,
                            onChange: handleImageStyleChange,
                            disabled: isDisabled
                        })
                    )
                ),

                el(PanelBody, { title: t('imageGenerationPanel', 'Image generation'), initialOpen: false },
                    el('p', { style: { fontSize: '12px', color: '#666', marginBottom: '12px' } },
                        t('imageGenerationDescription', 'Analyze the article, suggest images (1 featured + 5 inline), generate them with Gemini, and insert them into the post.')
                    ),
                    el(Button, {
                        isSecondary: true,
                        onClick: generateImagePrompts,
                        isBusy: imgLoading,
                        disabled: isDisabled,
                        style: { width: '100%', justifyContent: 'center', height: '40px', marginBottom: '8px' }
                    }, imgLoading ? t('analyzing', 'Analyzing...') : t('analyzeImagePromptsButton', '1. Analyze image prompts')),
                    renderImageSuggestions()
                ),

                el(PanelBody, { title: t('lastUsedInfoPanel', 'Last used information'), initialOpen: false },
                    el('div', { style: { fontSize: '12px' } },
                        el('p', {}, el('strong', {}, t('wordLabel', 'Keyword: ')), currentGenerationInfo.keyword || t('emptyValue', '(empty)')),
                        el('p', {}, el('strong', {}, t('notesLabel', 'Notes: ')), currentGenerationInfo.additionalNotes || t('emptyValue', '(empty)'))
                    )
                ),

                currentSources.length > 0 && el(PanelBody, { title: t('referenceUrlsPanel', 'Reference URLs'), initialOpen: true },
                    el('ul', { style: { margin: 0, paddingLeft: '16px', fontSize: '12px' } },
                        currentSources.map((src, idx) =>
                            el('li', { key: idx, style: { marginBottom: '6px', wordBreak: 'break-all' } },
                                src.title && el('div', { style: { fontWeight: 'bold', marginBottom: '2px' } }, src.title),
                                el('a', { href: src.url, target: '_blank', rel: 'noopener noreferrer' }, src.url)
                            )
                        )
                    )
                )
            )
        );
    };

    registerPlugin('picot-ai-seo-writer', {
        render: PICOT_SEO_WRITINGSidebar
    });
})();
