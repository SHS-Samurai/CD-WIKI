import { Editor } from "@tiptap/core";
import Link from "@tiptap/extension-link";
import { Table, TableCell, TableHeader, TableRow } from "@tiptap/extension-table";
import StarterKit from "@tiptap/starter-kit";


const EMPTY_DOCUMENT = {
  type: "doc",
  content: [{ type: "paragraph", content: [] }],
};

const COMMANDS = {
  paragraph: (editor) => editor.chain().focus().setParagraph().run(),
  "heading-1": (editor) => editor.chain().focus().toggleHeading({ level: 1 }).run(),
  "heading-2": (editor) => editor.chain().focus().toggleHeading({ level: 2 }).run(),
  bold: (editor) => editor.chain().focus().toggleBold().run(),
  italic: (editor) => editor.chain().focus().toggleItalic().run(),
  "bullet-list": (editor) => editor.chain().focus().toggleBulletList().run(),
  "ordered-list": (editor) => editor.chain().focus().toggleOrderedList().run(),
  blockquote: (editor) => editor.chain().focus().toggleBlockquote().run(),
  "code-block": (editor) => editor.chain().focus().toggleCodeBlock().run(),
  "horizontal-rule": (editor) => editor.chain().focus().setHorizontalRule().run(),
  table: (editor) => editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run(),
};

const ACTIVE_STATE = {
  paragraph: (editor) => editor.isActive("paragraph"),
  "heading-1": (editor) => editor.isActive("heading", { level: 1 }),
  "heading-2": (editor) => editor.isActive("heading", { level: 2 }),
  bold: (editor) => editor.isActive("bold"),
  italic: (editor) => editor.isActive("italic"),
  "bullet-list": (editor) => editor.isActive("bulletList"),
  "ordered-list": (editor) => editor.isActive("orderedList"),
  blockquote: (editor) => editor.isActive("blockquote"),
  "code-block": (editor) => editor.isActive("codeBlock"),
};

function readDocument(field) {
  try {
    const value = JSON.parse(field.value);
    return value && value.type === "doc" ? value : EMPTY_DOCUMENT;
  } catch {
    return EMPTY_DOCUMENT;
  }
}

function insertLink(editor) {
  const previous = editor.getAttributes("link").href || "";
  const href = window.prompt("Link-Ziel", previous);
  if (href === null) {
    return;
  }
  if (!href.trim()) {
    editor.chain().focus().extendMarkRange("link").unsetLink().run();
    return;
  }
  editor.chain().focus().extendMarkRange("link").setLink({ href: href.trim() }).run();
}

function insertWikiLink(editor) {
  const target = window.prompt("Wiki-Ziel als web/topic");
  if (!target) {
    return;
  }
  const parts = target.split("/").filter(Boolean);
  if (parts.length !== 2) {
    window.alert("Bitte das Ziel als web/topic eingeben.");
    return;
  }
  const href = `/w/${encodeURIComponent(parts[0])}/${encodeURIComponent(parts[1])}/`;
  if (editor.state.selection.empty) {
    editor.chain().focus().insertContent({
      type: "text",
      text: target,
      marks: [{ type: "link", attrs: { href } }],
    }).run();
    return;
  }
  editor.chain().focus().setLink({ href }).run();
}

function refreshToolbar(wrapper, editor) {
  wrapper.querySelectorAll("[data-editor-command]").forEach((button) => {
    const command = button.dataset.editorCommand;
    const isActive = ACTIVE_STATE[command]?.(editor) || false;
    button.classList.toggle("is-active", isActive);
    button.setAttribute("aria-pressed", String(isActive));
  });
}

function initialiseEditor(wrapper) {
  const field = wrapper.querySelector("textarea[name='content_json']");
  const surface = wrapper.querySelector("[data-editor-surface]");
  if (!field || !surface) {
    return;
  }

  const editor = new Editor({
    element: surface,
    content: readDocument(field),
    extensions: [
      StarterKit.configure({ link: false }),
      Link.configure({ openOnClick: false }),
      Table.configure({ resizable: true }),
      TableRow,
      TableHeader,
      TableCell,
    ],
    onCreate: ({ editor: instance }) => refreshToolbar(wrapper, instance),
    onSelectionUpdate: ({ editor: instance }) => refreshToolbar(wrapper, instance),
    onTransaction: ({ editor: instance }) => {
      field.value = JSON.stringify(instance.getJSON());
      refreshToolbar(wrapper, instance);
    },
  });

  wrapper.querySelectorAll("[data-editor-command]").forEach((button) => {
    button.addEventListener("click", () => {
      const command = button.dataset.editorCommand;
      if (command === "link") {
        insertLink(editor);
      } else if (command === "wiki-link") {
        insertWikiLink(editor);
      } else {
        COMMANDS[command]?.(editor);
      }
    });
  });

  const attachmentPicker = wrapper.querySelector("[data-editor-attachment]");
  attachmentPicker?.addEventListener("change", () => {
    const option = attachmentPicker.selectedOptions[0];
    if (!option?.value) {
      return;
    }
    editor.chain().focus().insertContent({
      type: "text",
      text: option.textContent,
      marks: [{ type: "link", attrs: { href: option.value } }],
    }).run();
    attachmentPicker.value = "";
  });

  wrapper.classList.add("editor-enabled");
}

document.querySelectorAll("[data-wiki-editor]").forEach(initialiseEditor);
