import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { Markdown } from '@tiptap/markdown';
import { TableKit } from '@tiptap/extension-table';
import Image from '@tiptap/extension-image';

const commands = {
    paragraph: editor => editor.chain().focus().setParagraph().run(),
    heading1: editor => editor.chain().focus().toggleHeading({ level: 1 }).run(),
    heading2: editor => editor.chain().focus().toggleHeading({ level: 2 }).run(),
    bold: editor => editor.chain().focus().toggleBold().run(),
    italic: editor => editor.chain().focus().toggleItalic().run(),
    bulletList: editor => editor.chain().focus().toggleBulletList().run(),
    orderedList: editor => editor.chain().focus().toggleOrderedList().run(),
    blockquote: editor => editor.chain().focus().toggleBlockquote().run(),
    codeBlock: editor => editor.chain().focus().toggleCodeBlock().run(),
    horizontalRule: editor => editor.chain().focus().setHorizontalRule().run(),
    undo: editor => editor.chain().focus().undo().run(),
    redo: editor => editor.chain().focus().redo().run(),
    table: editor => editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run(),
    link: editor => {
        const current = editor.getAttributes('link').href || '';
        const href = window.prompt('Zieladresse des Links:', current);
        if (href === null) return;
        if (href === '') {
            editor.chain().focus().extendMarkRange('link').unsetLink().run();
            return;
        }
        editor.chain().focus().extendMarkRange('link').setLink({ href }).run();
    },
    wikiLink: editor => {
        const title = window.prompt('Titel des Wiki-Artikels:');
        if (title?.trim()) editor.chain().focus().insertContent(`[[${title.trim()}]]`).run();
    },
};

const markdown = editor => editor.getMarkdown().replace(/\\\[\\\[([^\]\r\n]{1,180})\\\]\\\]/gu, '[[$1]]');

document.querySelectorAll('[data-wiki-editor]').forEach(container => {
    const textarea = container.querySelector('textarea');
    const surface = container.querySelector('[data-editor-surface]');
    const editor = new Editor({
        element: surface,
        extensions: [StarterKit, TableKit, Image.configure({ allowBase64: false }), Markdown],
        content: textarea.value,
        contentType: 'markdown',
        editorProps: {
            attributes: {
                class: 'wiki-editor-content',
            },
        },
        onUpdate: ({ editor }) => {
            textarea.value = markdown(editor);
        },
    });

    textarea.classList.add('hidden');
    surface.classList.remove('hidden');

    container.querySelectorAll('[data-editor-command]').forEach(button => {
        button.addEventListener('click', () => commands[button.dataset.editorCommand]?.(editor));
    });

    const uploadButton = container.querySelector('[data-image-upload]');
    uploadButton?.addEventListener('click', () => {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/jpeg,image/png,image/gif,image/webp';
        input.addEventListener('change', async () => {
            const file = input.files?.[0];
            if (!file) return;

            uploadButton.disabled = true;
            const formData = new FormData();
            formData.append('image', file);

            try {
                const response = await fetch(container.dataset.uploadUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Upload fehlgeschlagen.');
                }
                editor.chain().focus().setImage({ src: data.url, alt: data.alt || file.name }).run();
            } catch (error) {
                window.alert(error.message || 'Das Bild konnte nicht hochgeladen werden.');
            } finally {
                uploadButton.disabled = false;
            }
        });
        input.click();
    });

    textarea.form?.addEventListener('submit', () => {
        textarea.value = markdown(editor);
    });
});
