document.addEventListener('DOMContentLoaded', () => {
    // DOM Elements
    const sidebar = document.getElementById('sidebar');
    const toggleSidebarBtn = document.getElementById('toggle-sidebar');
    const fileList = document.getElementById('file-list');
    const newFileBtn = document.getElementById('new-file-btn');
    const refreshFilesBtn = document.getElementById('refresh-files');
    const currentFilenameDisplay = document.getElementById('current-filename');
    const previewArea = document.getElementById('preview-area');
    const modal = document.getElementById('modal');
    const modalCancel = document.getElementById('modal-cancel');
    const modalOk = document.getElementById('modal-ok');
    const newFilenameInput = document.getElementById('new-filename');
    const editorArea = document.getElementById('editor-area');
    const welcomeScreen = document.getElementById('welcome-screen');
    const exportMarkdownBtn = document.getElementById('export-markdown');
    const exportPdfBtn = document.getElementById('export-pdf');
    const exportTextBtn = document.getElementById('export-text');
    const copyMarkdownBtn = document.getElementById('copy-markdown');
    const copyHtmlBtn = document.getElementById('copy-html');
    const copyTextBtn = document.getElementById('copy-text');

    let currentFile = null;
    let paragraphs = []; // Array of { original: string, corrected: string, processing: boolean }
    let processTimeout = null;

    // Initialize EasyMDE
    const easyMDE = new EasyMDE({
        element: document.getElementById('markdown-editor'),
        spellChecker: false,
        autoDownloadFontAwesome: false,
        autosave: {
            enabled: false,
        },
        toolbar: ["bold", "italic", "heading", "|", "quote", "unordered-list", "ordered-list", "|", "link", "image", "|", "guide"],
        status: false,
        minHeight: "calc(100vh - 72px)", // Updated to match the CSS height of CodeMirror
        placeholder: "Write your markdown text here...",
    });

    // Configure marked.js to handle line breaks correctly
    marked.setOptions({
        breaks: true,
        gfm: true
    });

    // --- Sidebar & Files ---

    const toggleSidebar = () => {
        sidebar.classList.toggle('collapsed');
    };

    const loadFileList = async () => {
        try {
            const response = await fetch('api.php?action=list');
            const files = await response.json();
            fileList.innerHTML = '';
            files.forEach(file => {
                const li = document.createElement('li');
                li.className = 'p-3 hover:bg-gray-700 cursor-pointer border-b border-gray-700 transition flex justify-between items-center';
                li.innerHTML = `
                    <span class="truncate flex-1 flex items-center">
                        <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        ${file.name}
                    </span>
                    <span class="text-xs text-gray-500">${new Date(file.mtime * 1000).toLocaleDateString()}</span>
                `;
                li.onclick = () => loadFile(file.name);
                fileList.appendChild(li);
            });
        } catch (error) {
            console.error('Error loading file list:', error);
        }
    };

    const loadFile = async (name, updateUrl = true) => {
        try {
            const response = await fetch(`api.php?action=read&name=${encodeURIComponent(name)}`);
            const data = await response.json();
            if (data.content !== undefined) {
                currentFile = name;
                currentFilenameDisplay.textContent = name;
                
                // Update URL if requested
                if (updateUrl) {
                    const url = new URL(window.location);
                    url.searchParams.set('file', name);
                    window.history.pushState({ file: name }, '', url);
                }
                
                // Show editor and hide welcome screen
                welcomeScreen.classList.add('hidden');
                editorArea.classList.remove('hidden');
                
                easyMDE.value(data.content);
                // Reset paragraphs and load from content and revisions
                paragraphs = [];
                updateParagraphs(data.content, true, data.revisions || []);
            }
        } catch (error) {
            console.error('Error loading file:', error);
        }
    };

    const createNewFile = async () => {
        const name = newFilenameInput.value.trim();
        if (!name) return;

        try {
            const response = await fetch('api.php?action=create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name })
            });
            const data = await response.json();
            if (data.success) {
                modal.classList.add('hidden');
                newFilenameInput.value = '';
                await loadFileList();
                await loadFile(data.name);
            } else {
                alert(data.error || 'Error creating file');
            }
        } catch (error) {
            console.error('Error creating file:', error);
        }
    };

    const saveCurrentFile = async () => {
        if (!currentFile) return;
        const content = easyMDE.value();
        try {
            await fetch('api.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: currentFile, content })
            });
        } catch (error) {
            console.error('Error saving file:', error);
        }
    };

    const saveRevisions = async () => {
        if (!currentFile) return;
        try {
            await fetch('api.php?action=save_revisions', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: currentFile, revisions: paragraphs })
            });
        } catch (error) {
            console.error('Error saving revisions:', error);
        }
    };

    // --- AI Paragraph Processing ---

    const updateParagraphs = (fullText, isInitialLoad = false, savedRevisions = []) => {
        const newRawParagraphs = fullText.split(/\n\s*\n/).filter(p => p !== "");
        
        // Match new paragraphs with existing ones to preserve state
        let newParagraphsState = [];
        
        if (!isInitialLoad && newRawParagraphs.length === paragraphs.length) {
            // If length is same, match by index to handle editing existing paragraphs
            newParagraphsState = newRawParagraphs.map((raw, index) => {
                const p = paragraphs[index];
                if (p.original !== raw) {
                    p.original = raw;
                    p.dirty = true;
                }
                return p;
            });
        } else if (isInitialLoad && savedRevisions.length > 0) {
            // During initial load, try to match saved revisions by exact original text
            newParagraphsState = newRawParagraphs.map(raw => {
                const saved = savedRevisions.find(p => p.original === raw);
                if (saved) {
                    return {
                        original: saved.original,
                        corrected: saved.corrected,
                        processing: false,
                        dirty: false
                    };
                } else {
                    return {
                        original: raw,
                        corrected: raw,
                        processing: false,
                        dirty: true
                    };
                }
            });
        } else {
            // If length changed or initial load without saved revisions, try to match by exact content
            newParagraphsState = newRawParagraphs.map(raw => {
                const existing = paragraphs.find(p => p.original === raw);
                if (existing) {
                    return existing;
                } else {
                    return {
                        original: raw,
                        corrected: raw,
                        processing: false,
                        dirty: !isInitialLoad || (isInitialLoad && savedRevisions.length === 0)
                    };
                }
            });
        }

        paragraphs = newParagraphsState;
        renderPreview();

        // Process dirty paragraphs
        paragraphs.forEach((p, index) => {
            if (p.dirty && !p.processing) {
                processParagraphWithAI(index);
            }
        });
    };

    const processParagraphWithAI = async (index) => {
        const p = paragraphs[index];
        if (!p || !p.original.trim()) return;

        p.processing = true;
        p.dirty = false;
        renderPreview();

        try {
            const response = await fetch('api.php?action=process_paragraph', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ text: p.original })
            });
            const data = await response.json();
            if (data.corrected) {
                p.corrected = data.corrected;
                saveRevisions();
            }
        } catch (error) {
            console.error('Error during AI processing:', error);
        } finally {
            p.processing = false;
            renderPreview();
        }
    };

    const acceptRevision = (index) => {
        const p = paragraphs[index];
        if (!p || p.processing) return;

        p.original = p.corrected;
        p.dirty = false;

        const newFullText = paragraphs.map(par => par.original).join('\n\n');
        easyMDE.value(newFullText);
        saveCurrentFile();
        saveRevisions();
        renderPreview();
    };

    const renderPreview = () => {
        previewArea.innerHTML = '';
        paragraphs.forEach((p, index) => {
            const isChanged = !p.processing && p.original.trim() !== p.corrected.trim();

            // Eine Zeile: links Pfeil (zwischen den Spalten), rechts KI-Überarbeitung
            const row = document.createElement('div');
            row.className = 'preview-row flex items-stretch gap-3 xxpy-3 border-b border-gray-200 last:border-b-0';

            const arrowCell = document.createElement('div');
            arrowCell.className = 'w-10 flex-shrink-0 flex items-center justify-center arrow-cell';
            if (isChanged) {
                const btn = document.createElement('button');
                btn.innerHTML = `
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                `;
                btn.className = 'bg-blue-600 text-white w-10 h-10 rounded-full flex items-center justify-center hover:bg-blue-700 shadow-md z-10 cursor-pointer transition-all hover:scale-110 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2';
                btn.title = 'Apply revision to editor';
                btn.onclick = (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    acceptRevision(index);
                };
                arrowCell.appendChild(btn);
            }
            row.appendChild(arrowCell);

            const container = document.createElement('div');
            container.className = `relative group flex-1 min-w-0 p-3 ${isChanged ? 'bg-blue-50/50 rounded-lg' : ''} transition-all`;

            const contentDiv = document.createElement('div');
            contentDiv.className = 'prose prose-slate max-w-none';
            if (p.processing) {
                contentDiv.classList.add('animate-pulse', 'opacity-50');
                contentDiv.innerHTML = marked.parse(p.original);
            } else {
                contentDiv.innerHTML = marked.parse(p.corrected);
            }

            container.appendChild(contentDiv);
            row.appendChild(container);
            previewArea.appendChild(row);
        });
    };

    const getFullCorrectedMarkdown = () => {
        return paragraphs.map(p => p.processing ? p.original : p.corrected).join('\n\n');
    };

    const getFullCorrectedHtml = () => {
        const md = getFullCorrectedMarkdown();
        return marked.parse(md);
    };

    const getFullCorrectedText = () => {
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = getFullCorrectedHtml();
        return tempDiv.innerText || tempDiv.textContent || "";
    };

    const downloadFile = (content, filename, contentType) => {
        const a = document.createElement('a');
        const file = new Blob([content], { type: contentType });
        a.href = URL.createObjectURL(file);
        a.download = filename;
        a.click();
        URL.revokeObjectURL(a.href);
    };

    const copyToClipboard = async (text, type = 'text/plain') => {
        try {
            if (type === 'text/html') {
                const blob = new Blob([text], { type: 'text/html' });
                const data = [new ClipboardItem({ 'text/html': blob, 'text/plain': new Blob([getFullCorrectedText()], { type: 'text/plain' }) })];
                await navigator.clipboard.write(data);
            } else {
                await navigator.clipboard.writeText(text);
            }
            // Optional: Show a short success message
            const originalText = event?.target?.innerText || 'Copied';
            if (event?.target && event.target.tagName === 'BUTTON') {
                const btn = event.target;
                const oldHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check mr-2 text-green-500"></i> Copied!';
                setTimeout(() => { btn.innerHTML = oldHtml; }, 2000);
            }
        } catch (err) {
            console.error('Error copying:', err);
        }
    };

    // --- Events ---

    exportMarkdownBtn.onclick = () => {
        const content = getFullCorrectedMarkdown();
        const filename = (currentFile || 'export.md').replace(/\.[^/.]+$/, "") + ".md";
        downloadFile(content, filename, 'text/markdown');
    };

    exportTextBtn.onclick = () => {
        const content = getFullCorrectedText();
        const filename = (currentFile || 'export.txt').replace(/\.[^/.]+$/, "") + ".txt";
        downloadFile(content, filename, 'text/plain');
    };

    exportPdfBtn.onclick = () => {
        const element = document.createElement('div');
        element.className = 'prose prose-slate max-w-none p-8';
        element.innerHTML = getFullCorrectedHtml();
        
        const opt = {
            margin: 10,
            filename: (currentFile || 'export.pdf').replace(/\.[^/.]+$/, "") + ".pdf",
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2 },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        
        html2pdf().set(opt).from(element).save();
    };

    copyMarkdownBtn.onclick = (e) => {
        copyToClipboard(getFullCorrectedMarkdown());
    };

    copyHtmlBtn.onclick = (e) => {
        copyToClipboard(getFullCorrectedHtml(), 'text/html');
    };

    copyTextBtn.onclick = (e) => {
        copyToClipboard(getFullCorrectedText());
    };

    toggleSidebarBtn.onclick = toggleSidebar;
    refreshFilesBtn.onclick = loadFileList;
    
    newFileBtn.onclick = () => {
        modal.classList.remove('hidden');
        newFilenameInput.focus();
    };
    
    modalCancel.onclick = () => {
        modal.classList.add('hidden');
        newFilenameInput.value = '';
    };

    modalOk.onclick = createNewFile;
    newFilenameInput.onkeyup = (e) => { if (e.key === 'Enter') createNewFile(); };

    easyMDE.codemirror.on('change', () => {
        if (processTimeout) clearTimeout(processTimeout);
        processTimeout = setTimeout(() => {
            updateParagraphs(easyMDE.value());
            saveCurrentFile();
        }, 1000);
    });

    // Initial Load
    loadFileList();

    // Check URL for file parameter
    const urlParams = new URLSearchParams(window.location.search);
    const fileParam = urlParams.get('file');
    if (fileParam) {
        loadFile(fileParam, false);
    }

    // Handle browser back/forward
    window.onpopstate = (event) => {
        if (event.state && event.state.file) {
            loadFile(event.state.file, false);
        } else {
            // No file in state, maybe go back to welcome screen?
            const urlParams = new URLSearchParams(window.location.search);
            const fileParam = urlParams.get('file');
            if (fileParam) {
                loadFile(fileParam, false);
            } else {
                currentFile = null;
                currentFilenameDisplay.textContent = 'No file selected';
                welcomeScreen.classList.remove('hidden');
                editorArea.classList.add('hidden');
            }
        }
    };
});
