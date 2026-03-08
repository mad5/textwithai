# TextWithAI - AI-Powered Markdown Editor

**TextWithAI** is a lightweight, web-based Markdown editor that uses artificial intelligence to correct, optimize, and support the writing process in real-time. The application is designed to work either locally with **Ollama** or via the **OpenAI API**.

## 🚀 Benefits & Features

- **Real-time AI Assistance:** Correction of spelling, grammar, and style directly within the editor.
- **AI Assistants & Personas:** Choose from various assistant styles (e.g., Concise, Diplomatic, Persuasive, Poet, Simplify) to rewrite your text in specific tones.
- **Custom AI Prompts:** Manually instruct the AI on how to rewrite specific paragraphs (e.g., "Summarize this" or "Make it more formal").
- **PWA Support:** Install TextWithAI as a Progressive Web App (PWA) on your desktop or mobile device for a native-like experience.
- **Seamless Markdown Experience:** Uses the proven EasyMDE editor for a comfortable writing environment.
- **Local or Cloud AI:** Full flexibility between data privacy (local via Ollama) and performance (OpenAI).
- **Advanced File Management:**
    - Create, save, and load Markdown files.
    - Organize files in user-specific subdirectories (based on Browser UUID).
    - Easy file renaming and refreshing the file list.
- **Version History & Revisions:** Track corrections and changes within documents; revert to original text easily.
- **Versatile Export & Copy Options:**
    - Export documents as Markdown (.md), PDF, or plain text (.txt).
    - Quick copy to clipboard as Markdown, HTML, or plain text.
- **Responsive Design:** Optimized for both desktop and mobile use with a clean, modern interface using Tailwind CSS.
- **Simple Installation:** No database required – runs on any PHP-enabled web server.

![Preview](preview.png)

---

## 🛠 Prerequisites

Before installing TextWithAI, ensure your system meets the following requirements:

1. **Web Server:** Apache or Nginx with PHP support.
2. **PHP:** Version 8.0 or higher.
3. **AI Backend:**
   - **Ollama** (for local execution) – [Ollama Website](https://ollama.com/)
   - **OR** an **OpenAI API Key**.
4. **Write Permissions:** The web server needs write access to the `storage/` directory.

---

## 📦 Installation

1. **Clone or Download the Repository:**
   Download the source code into your web directory (e.g., `/var/www/html/textwithai`).
   ```bash
   git clone https://github.com/YourUsername/textwithai.git .
   ```

2. **Check Directory Structure:**
   Ensure that the `storage` and `assistants` directories exist.
   ```bash
   mkdir -p storage assistants
   ```

3. **Set Permissions:**
   Grant the web server user (e.g., `www-data`) write permissions for the storage folder:
   ```bash
   chmod -R 775 storage
   chown -R www-data:www-data storage
   ```

---

## ⚙️ Setup & Configuration

All configuration is done via the `config.php` file.

1. **Create Configuration File:**
   Copy the example file:
   ```bash
   cp config.example.php config.php
   ```

2. **Adjust the File:**
   Open `config.php` with a text editor of your choice and adjust the values:

   ```php
   <?php
   $config = [
       "llm" => "ollama",           // "ollama" or "openai"
       
       // Ollama Settings (Local)
       "ollama_url"    => "http://localhost:11434",
       "ollama_model"  => "gemma3:12b",
       
       // OpenAI Settings (Cloud)
       "openai_key"    => "sk-proj-...",
       "openai_model"  => "gpt-4o",
       
       "storage"       => __DIR__.'/storage',
       "userid"        => "browser", // "browser" = UUID per browser, else "default"
       "system_prompt" => "Maintain meaning and markdown format. Respond ONLY with corrected text.",
   ];
   return $config;
   ```

### Key Parameters:
- `llm`: Determines which service is used for AI processing.
- `ollama_url`: The address of your local Ollama server.
- `openai_key`: Your secret API key from OpenAI (only required if `llm` is set to `openai`).
- `userid`: Setting this to `"browser"` enables multi-user support where each browser gets its own storage folder.
- `system_prompt`: Define how the AI should behave globally (e.g., language, tone).

### URL Parameters:
You can use the following parameters in the URL to control the behavior of the application:
- `fileid`: Loads a specific file directly on startup (e.g., `?fileid=MyDocument`). If the file does not exist, it will be created automatically.
- `public`: Use `?public=1` to use a shared public storage folder instead of a private user-specific directory.
- `hidetop`: Use `?hidetop=1` to hide the top navigation bar (ideal for embedding the editor).

---

## 📖 Usage

1. Access the application in your browser (e.g., `http://localhost/textwithai`).
2. Create a new file using the **"New"** button at the top right.
3. Select a file from the left sidebar.
4. Write your text in the left editor area.
5. **AI Interaction:**
    - The AI automatically processes your paragraphs and displays suggestions in the preview area.
    - Use the **Assistant dropdown** to change the persona/style of the AI.
    - Click on a paragraph in the preview to enter a **custom prompt** for specific adjustments.
6. **Management:**
    - Click the filename in the header to **rename** the current file.
    - Use the **Copy** button to copy your text in different formats (Markdown, HTML, Text).
    - Use the **Export** button to download your finished work as .md, .pdf, or .txt.

---

## 🔒 Security Notes

- `config.php` contains sensitive data (like API keys) and should never be publicly accessible.
- In the default configuration, the `.gitignore` file prevents uploading `config.php` to Git repositories.
- Ensure that your `storage` folder is protected from direct HTTP access (e.g., via a `.htaccess` file with `Deny from all`).

---

## 📄 License

This project is released under the MIT License. For more information, see the [LICENSE](LICENSE) file (if available).
