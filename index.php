<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TextWithAI - Markdown Editor</title>
    <!-- Tailwind CSS & Typography -->
    <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- EasyMDE -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
    <script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
    <!-- Marked.js -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <!-- html2pdf.js for PDF export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        .editor-container .CodeMirror {
            height: calc(100vh - 72px);
            font-size: 1.1rem;
            border-top: none;
            border-left: none;
            border-bottom: none;
        }
        .editor-container {
            padding: 0 !important;
        }
        .sidebar {
            transition: width 0.3s;
            width: 260px;
        }
        .sidebar.collapsed {
            width: 0;
            overflow: hidden;
        }
        .main-content {
            transition: margin-left 0.3s;
        }
        #preview-area {
            height: calc(100vh - 64px - 53px); /* 64px header, 53px toolbar */
            overflow-y: auto;
        }
        .loading-paragraph {
            opacity: 0.5;
            background: #f0f0f0;
            padding: 5px;
            border-radius: 4px;
        }
    </style>
</head>
<body class="bg-gray-100 h-screen flex flex-col">

    <!-- Header -->
    <header class="bg-blue-600 text-white p-4 flex justify-between items-center shadow-md">
        <div class="flex items-center">
            <button id="toggle-sidebar" class="mr-4 text-xl">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
            </button>
            <h1 class="text-xl font-bold">TextWithAI</h1>
        </div>
        <div>
            <span id="current-filename" class="italic mr-4">No file selected</span>
            <button id="new-file-btn" class="bg-green-500 hover:bg-green-600 px-4 py-2 rounded text-white transition inline-flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                New
            </button>
        </div>
    </header>

    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar bg-gray-800 text-white flex flex-col shadow-inner">
            <div class="p-4 font-semibold border-b border-gray-700 flex justify-between items-center">
                <span>Files</span>
                <button id="refresh-files" class="text-gray-400 hover:text-white">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                </button>
            </div>
            <ul id="file-list" class="flex-1 overflow-y-auto">
                <!-- File list will be inserted here -->
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col overflow-hidden">
            <!-- Welcome Screen (Placeholder) -->
            <div id="welcome-screen" class="flex-1 flex flex-col items-center justify-center bg-gray-50 text-gray-500">
                <svg class="w-16 h-16 mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h2 class="text-2xl font-bold mb-2">Welcome to TextWithKI</h2>
                <p>Select a file from the sidebar or create a new one.</p>
            </div>

            <div id="editor-area" class="flex-1 flex overflow-hidden hidden">
                <!-- Editor Side (Left) -->
                <div class="w-1/2 flex flex-col border-r border-gray-300 editor-container bg-white">
                    <textarea id="markdown-editor"></textarea>
                </div>
                <div class="flex-1 flex flex-col bg-gray-50 relative min-w-0">
                    <!-- Preview Toolbar -->
                    <div class="bg-white border-b border-gray-300 p-2 flex justify-between items-center shadow-sm z-10">
                        <div class="flex space-x-2">
                            <div class="relative group">
                                <button class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm flex items-center transition">
                                    <i class="fas fa-download mr-1"></i> Export <i class="fas fa-chevron-down ml-1 text-xs"></i>
                                </button>
                                <div class="absolute left-0 mt-1 w-40 bg-white border border-gray-200 rounded shadow-lg hidden group-hover:block z-20">
                                    <button id="export-markdown" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 flex items-center">
                                        <i class="fab fa-markdown mr-2 text-blue-500"></i> Markdown (.md)
                                    </button>
                                    <button id="export-pdf" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 flex items-center">
                                        <i class="fas fa-file-pdf mr-2 text-red-500"></i> PDF (.pdf)
                                    </button>
                                    <button id="export-text" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 flex items-center">
                                        <i class="fas fa-file-alt mr-2 text-gray-500"></i> Text (.txt)
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <div class="relative group">
                                <button class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1 rounded text-sm flex items-center transition">
                                    <i class="fas fa-copy mr-1"></i> Copy <i class="fas fa-chevron-down ml-1 text-xs"></i>
                                </button>
                                <div class="absolute right-0 mt-1 w-48 bg-white border border-gray-200 rounded shadow-lg hidden group-hover:block z-20">
                                    <button id="copy-markdown" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 flex items-center">
                                        <i class="fab fa-markdown mr-2 text-blue-500"></i> As Markdown
                                    </button>
                                    <button id="copy-html" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 flex items-center">
                                        <i class="fas fa-code mr-2 text-orange-500"></i> As HTML
                                    </button>
                                    <button id="copy-text" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 flex items-center">
                                        <i class="fas fa-align-left mr-2 text-gray-500"></i> As Plain Text
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="preview-area" class="px-4 py-4 flex-1 overflow-y-auto">
                        <!-- Pro Absatz: eine Zeile mit Pfeil (links) + Karte (rechts) -->
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal for new filename -->
    <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white p-6 rounded-lg shadow-xl w-96">
            <h3 class="text-lg font-bold mb-4">Create New File</h3>
            <input type="text" id="new-filename" class="w-full border p-2 mb-4 rounded border-gray-300 focus:border-blue-500 outline-none" placeholder="Filename (e.g. note.md)">
            <div class="flex justify-end gap-2">
                <button id="modal-cancel" class="bg-gray-300 hover:bg-gray-400 px-4 py-2 rounded">Cancel</button>
                <button id="modal-ok" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Create</button>
            </div>
        </div>
    </div>

    <script src="app.js?_=<?= time();?>"></script>
</body>
</html>
