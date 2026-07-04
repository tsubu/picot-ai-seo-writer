(function() {
    'use strict';

    const { registerPlugin } = wp.plugins;
    const { PluginSidebar } = wp.editPost;
    const { PanelBody, TextControl, TextareaControl, SelectControl, Button } = wp.components;
    const { useState, useEffect, createElement: el } = wp.element;

    const getOverlaySubmessage = (config) => {
        const strings = (config && config.strings) || {};
        return strings.overlaySubmessage || 'これには数十秒かかる場合があります。';
    };

    const PICOT_SEO_WRITINGSidebar = () => {
        const config = window.picotSeoWriting || window.picot_seo_writing_admin || {};

        const [keyword, setKeyword]             = useState(config.lastKeyword || config.target_keyword || '');
        const [additionalNotes, setAdditionalNotes] = useState(config.lastNotes || config.additional_notes || '');
        const [writingStyle, setWritingStyle]   = useState(config.currentStyle || 'casual');
        const [imageStyle, setImageStyle]       = useState(config.currentImageStyle || 'photorealistic');
        const [language, setLanguage]           = useState('japanese');
        const [loading, setLoading]             = useState(false);
        const [imgLoading, setImgLoading]       = useState(false);
        const [imgSuccess, setImgSuccess]       = useState(!!config.imageSuggestions);
        const [message, setMessage]             = useState({ text: '', type: '' });
        const [imageSuggestions, setImageSuggestions] = useState(config.imageSuggestions || null); // { featured_prompt, featured_text, suggestions[] }
        const [generatingIdx, setGeneratingIdx] = useState(-1); // 現在生成中のインデックス（-2=featured）
        const [bulkInfo, setBulkInfo]           = useState(null); // 一括生成時の情報 { current: 1, total: 6 }
        const [completedImages, setCompletedImages] = useState([]); // 生成完了した画像のインデックスの配列

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

            let msg = 'AIが記事を生成中...';
            if (imgLoading) msg = '画像プロンプトを分析中...';
            else if (bulkInfo) msg = `全 ${bulkInfo.total} 枚中 ${bulkInfo.current} 枚目の画像を生成中...`;
            else if (generatingIdx === -2) msg = 'アイキャッチ画像を生成中...';
            else if (generatingIdx >= 0) msg = `画像 ${generatingIdx + 1} を生成中...`;

            window.PicotSeoWritingOverlay.show(msg, getOverlaySubmessage(config));

            return () => {
                if (window.PicotSeoWritingOverlay) {
                    window.PicotSeoWritingOverlay.hide();
                }
            };
        }, [loading, imgLoading, generatingIdx, bulkInfo]);

        // ─── 記事生成 ───────────────────────────────────────────
        const generateArticle = () => {
            if (!keyword.trim()) {
                setMessage({ text: 'ターゲットワードを入力してください', type: 'error' });
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
                data: { keyword, additional_notes: additionalNotes, writing_style: writingStyle, language, post_id: config.postId }
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
                    setMessage({ text: '記事を生成しました！', type: 'success' });
                } else {
                    setMessage({ text: r.error || 'エラーが発生しました', type: 'error' });
                }
            })
            .catch(err => {
                const msg = err.message || (err.data && err.data.message) || '不明なエラー';
                setMessage({ text: 'エラー: ' + msg, type: 'error' });
            })
            .finally(() => setLoading(false));
        };

        // ─── 画像プロンプト挿入 ──────────────────────────────────
        const generateImagePrompts = () => {
            const content = wp.data.select('core/editor').getEditedPostAttribute('content');
            if (!content || !content.trim()) {
                setMessage({ text: '先に記事を生成してください', type: 'error' });
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
                    setMessage({ text: '画像提案を取得しました！下のリストから生成してください。', type: 'success' });
                } else {
                    setMessage({ text: (data && data.message) || '画像プロンプト挿入に失敗しました', type: 'error' });
                }
            })
            .catch(err => {
                const msg = (err && err.message) ? err.message : JSON.stringify(err);
                setMessage({ text: '通信エラー: ' + msg, type: 'error' });
            })
            .finally(() => setImgLoading(false));
        };

        // ─── 単一画像の生成・挿入 ────────────────────────────────
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
                    throw new Error(r.message || '画像データが返されませんでした');
                }

                if (isFeatured) {
                    // アイキャッチ画像として設定
                    wp.data.dispatch('core/editor').editPost({ featured_media: r.attachment_id });
                    
                    // タイトル下（ブロックの先頭）にも画像ブロックとして挿入
                    const imageBlock = wp.blocks.createBlock('core/image', {
                        id: r.attachment_id,
                        url: r.url,
                        alt: description || '',
                        caption: '',
                    });
                    wp.data.dispatch('core/block-editor').insertBlocks(imageBlock, 0);
                    
                    setMessage({ text: 'アイキャッチ画像を設定・挿入しました！', type: 'success' });
                } else {
                    // 本文の適切なブロックの後に挿入
                    insertImageBlock(r.attachment_id, r.url, description, location);
                    setMessage({ text: '画像を記事に挿入しました！', type: 'success' });
                }
                setCompletedImages(prev => [...prev, isFeatured ? -2 : idx]);
            } catch (err) {
                const msg = (err && err.message) ? err.message : JSON.stringify(err);
                setMessage({ text: '画像生成エラー: ' + msg, type: 'error' });
            } finally {
                setGeneratingIdx(-1);
            }
        };

        // ─── 全てのブロック（ネスト含む）をフラットなリストで取得 ────────────────
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

        // ─── 画像ブロックを適切な位置に挿入 ─────────────────────
        const insertImageBlock = (attachmentId, url, alt, locationText) => {
            const imageBlock = wp.blocks.createBlock('core/image', {
                id: attachmentId,
                url,
                alt: alt || '',
                caption: '',
            });

            // 記号や空白を除去して正規化する関数
            const normalize = (txt) => {
                return (txt || '')
                    .replace(/<[^>]*>/g, '') // HTML除去
                    .replace(/[ \s　.,!?:;()[\]{}<>"'|\\/_~`\-+*=&%$#@^!！。、？：；（）［］｛｝＜＞”’｜￥＿〜｀－＋＊＝＆％＄＃＠＾]/g, '') // 記号・空白除去
                    .toLowerCase();
            };

            const rootBlocks = wp.data.select('core/block-editor').getBlocks();
            let insertIdx = rootBlocks.length; // デフォルトは末尾
            let targetClientId = null; // 指定があればそのブロックの後に挿入

            if (locationText && locationText.trim()) {
                const searchNorm = normalize(locationText);
                if (searchNorm) {
                    // 全てのブロック（ネストされたものも含む）を検索対象にする
                    const allBlocks = getAllBlocks(rootBlocks);
                    const searchPart = searchNorm.slice(0, 50);
                    
                    console.log('[Picot SEO] Searching for location:', locationText);
                    console.log('[Picot SEO] Normalized search text:', searchPart);

                    for (let block of allBlocks) {
                        const attrs = block.attributes || {};
                        const blockTextNorm = normalize(attrs.content || attrs.value || '');
                        
                        if (blockTextNorm && (blockTextNorm.includes(searchPart) || searchPart.includes(blockTextNorm))) {
                            targetClientId = block.clientId;
                            console.log('[Picot SEO] Match found! Block ClientID:', targetClientId);
                            break;
                        }
                    }
                }
            }

            if (targetClientId) {
                const editorSelect = wp.data.select('core/block-editor');
                
                // 最上位の親（ルート直下のブロック）を特定する
                let topLevelId = targetClientId;
                let currentParentId = editorSelect.getBlockRootClientId(topLevelId);
                
                while (currentParentId) {
                    topLevelId = currentParentId;
                    currentParentId = editorSelect.getBlockRootClientId(topLevelId);
                }

                // 最上位階層でのインデックスを取得
                const index = editorSelect.getBlockIndex(topLevelId);
                
                console.log(`[Picot SEO] Inserting at root level after top-level block at index ${index} to ensure visibility.`);
                // 常にルート（undefined）に挿入することで、表示されない問題を100%回避
                wp.data.dispatch('core/block-editor').insertBlocks([imageBlock], index + 1, undefined, true);
                
                // スクロールして確認
                setTimeout(() => {
                    const el = document.querySelector(`[data-block="${imageBlock.clientId}"]`);
                    if (el) {
                        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }, 500);
            } else {
                // 見つからない場合は末尾
                console.warn('[Picot SEO] Location not found. Appending to end.');
                const allRootBlocks = wp.data.select('core/block-editor').getBlocks();
                wp.data.dispatch('core/block-editor').insertBlocks([imageBlock], allRootBlocks.length, undefined, true);
            }
        };

        // ─── 全画像を一括生成 ────────────────────────────────────
        const generateAllImages = async () => {
            if (!imageSuggestions) return;
            setMessage({ text: '全画像を生成中...（時間がかかります）', type: 'success' });

            const allItems = [
                { prompt: imageSuggestions.featured_prompt, description: imageSuggestions.featured_text, location: '', isFeatured: true, idx: -2 },
                ...(imageSuggestions.suggestions || []).map((s, i) => ({ ...s, isFeatured: false, idx: i }))
            ];
            
            const pendingItems = allItems.filter(item => !completedImages.includes(item.idx));
            
            if (pendingItems.length === 0) {
                setMessage({ text: 'すべての画像が生成済みです！', type: 'success' });
                return;
            }

            setBulkInfo({ current: 1, total: pendingItems.length });

            for (let i = 0; i < pendingItems.length; i++) {
                const item = pendingItems[i];
                setBulkInfo({ current: i + 1, total: pendingItems.length });
                await generateSingleImage(item.prompt, item.description, item.location, item.isFeatured, item.idx);
                // エディタの更新を待つために長めに待機
                await new Promise(r => setTimeout(r, 1000));
            }
            
            setBulkInfo(null);
            setMessage({ text: '全画像の生成・挿入が完了しました！', type: 'success' });
        };

        const isDisabled = loading || imgLoading || generatingIdx !== -1;

        // ─── 画像提案リストの描画 ─────────────────────────────────
        const renderImageSuggestions = () => {
            if (!imageSuggestions) return null;
            const { featured_prompt, featured_text, suggestions = [] } = imageSuggestions;

            return el('div', { style: { marginTop: '12px' } },
                // アイキャッチ
                el('div', {
                    style: {
                        border: '2px solid #f56e28', borderRadius: '6px',
                        padding: '10px', marginBottom: '8px', background: '#fff8f5'
                    }
                },
                    el('strong', { style: { fontSize: '12px', color: '#f56e28' } }, '⭐ アイキャッチ画像'),
                    el('p', { style: { fontSize: '11px', margin: '4px 0', color: '#555' } }, featured_text),
                    !completedImages.includes(-2) ? el(Button, {
                        isSecondary: true,
                        onClick: () => generateSingleImage(featured_prompt, featured_text, '', true, -2),
                        isBusy: generatingIdx === -2,
                        disabled: isDisabled,
                        style: { width: '100%', justifyContent: 'center', marginTop: '6px', height: '32px', fontSize: '12px' }
                    }, generatingIdx === -2 ? '生成中...' : '🖼️ 生成して設定') : el('div', { style: { color: '#155724', fontSize: '12px', marginTop: '6px', textAlign: 'center', fontWeight: 'bold' } }, '✅ 生成完了')
                ),
                // 本文画像
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
                        }, generatingIdx === i ? '生成中...' : '🖼️ 生成して挿入') : el('div', { style: { color: '#155724', fontSize: '11px', marginTop: '4px', textAlign: 'center', fontWeight: 'bold' } }, '✅ 生成完了')
                    )
                ),
                // 一括生成ボタン
                el(Button, {
                    isPrimary: true,
                    onClick: generateAllImages,
                    disabled: isDisabled,
                    style: {
                        width: '100%', justifyContent: 'center', marginTop: '8px', height: '40px',
                        background: '#1d2327', borderColor: '#1d2327'
                    }
                }, '⚡ 全ての画像を生成して挿入')
            );
        };

        return el(PluginSidebar, {
            name: 'picot-ai-seo-writer-sidebar',
            title: 'Picot AI SEO Writer',
            icon: el('span', { className: 'dashicons dashicons-admin-appearance' })
        },
            el('div', { className: 'picot-sidebar-content', style: { padding: '16px' } },

                // ── 記事生成パネル ──
                el(PanelBody, { title: '記事生成設定', initialOpen: true },
                    el('div', { style: { marginBottom: '20px' } },
                        el(TextControl, {
                            label: 'ターゲットワード',
                            value: keyword,
                            onChange: handleKeywordChange,
                            placeholder: '例: WordPress SEO',
                            disabled: isDisabled
                        })
                    ),
                    el('div', { style: { marginBottom: '20px' } },
                        el(TextareaControl, {
                            label: '希望追加内容（任意）',
                            value: additionalNotes,
                            onChange: handleNotesChange,
                            rows: 5,
                            placeholder: '記事に含めたい具体的な情報や要望を入力してください',
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
                        }, loading ? '生成中...' : '記事を生成')
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

                // ── 執筆スタイルパネル ──
                el(PanelBody, { title: '執筆スタイル', initialOpen: false },
                    el('div', { style: { marginBottom: '20px' } },
                        el(SelectControl, {
                            label: '文章スタイル',
                            value: writingStyle,
                            options: [
                                { label: 'カジュアル', value: 'casual' },
                                { label: 'プロフェッショナル', value: 'professional' },
                                { label: 'フレンドリー', value: 'friendly' },
                                { label: '専門的', value: 'technical' },
                            ],
                            onChange: handleStyleChange,
                            disabled: isDisabled
                        })
                    ),
                    el('div', { style: { marginBottom: '10px' } },
                        el(SelectControl, {
                            label: '画像スタイル',
                            value: imageStyle,
                            options: [
                                { label: '実写風 (Photorealistic)', value: 'photorealistic' },
                                { label: 'デジタルアート', value: 'digital_art' },
                                { label: 'ベクターイラスト', value: 'vector' },
                                { label: 'スケッチ風', value: 'sketch' },
                                { label: '水彩画風', value: 'watercolor' },
                                { label: 'サイバーパンク', value: 'cyberpunk' },
                                { label: 'アニメ風', value: 'anime' },
                                { label: '油絵風', value: 'oil_painting' },
                            ],
                            onChange: handleImageStyleChange,
                            disabled: isDisabled
                        })
                    )
                ),

                // ── 画像生成パネル ──
                el(PanelBody, { title: '画像生成', initialOpen: false },
                    el('p', { style: { fontSize: '12px', color: '#666', marginBottom: '12px' } },
                        '記事を分析して画像提案(1 アイキャッチ + 5 本文)を生成し、Gemini で画像を生成して記事に挿入します。'
                    ),
                    el(Button, {
                        isSecondary: true,
                        onClick: generateImagePrompts,
                        isBusy: imgLoading,
                        disabled: isDisabled,
                        style: { width: '100%', justifyContent: 'center', height: '40px', marginBottom: '8px' }
                    }, imgLoading ? '分析中...' : '① 画像プロンプトを分析'),
                    renderImageSuggestions()
                ),

                // ── 前回の生成情報 ──
                el(PanelBody, { title: '前回使用した情報', initialOpen: false },
                    el('div', { style: { fontSize: '12px' } },
                        el('p', {}, el('strong', {}, 'ワード: '), currentGenerationInfo.keyword || '(空)'),
                        el('p', {}, el('strong', {}, '要望: '), currentGenerationInfo.additionalNotes || '(空)')
                    )
                ),

                currentSources.length > 0 && el(PanelBody, { title: '参照URL一覧', initialOpen: true },
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
