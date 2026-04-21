import { useEffect } from 'react';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import { Bold, Italic, List, ListOrdered, Undo2, Redo2 } from 'lucide-react';
import { cn } from '@/lib/cn';

interface Props {
  value: string;
  onChange: (html: string) => void;
  disabled?: boolean;
  placeholder?: string;
  ariaLabel?: string;
}

/**
 * TipTap-backed HTML editor. Replaces the legacy Trix editor while
 * preserving bidirectional HTML compatibility — existing descriptions
 * stored by the Blade admin round-trip unchanged. StarterKit covers
 * the same toolbar surface the legacy Trix ran with (bold, italic,
 * ordered/unordered lists, headings, undo/redo).
 */
export function RichTextEditor({
  value,
  onChange,
  disabled,
  placeholder = 'Write a description…',
  ariaLabel = 'Description editor',
}: Props) {
  const editor = useEditor({
    extensions: [StarterKit, Placeholder.configure({ placeholder })],
    content: value || '',
    editable: !disabled,
    onUpdate: ({ editor }) => onChange(editor.getHTML()),
    editorProps: {
      attributes: {
        'aria-label': ariaLabel,
        role: 'textbox',
        'aria-multiline': 'true',
        class:
          'prose prose-sm max-w-none min-h-[140px] rounded-b-lg bg-elevated px-3 py-2 text-[13px] text-fg focus:outline-none',
      },
    },
  });

  // Sync external value changes (edit dialog prefill, duplicate).
  // Guard: only set when the incoming value differs from the editor's
  // current HTML — otherwise we'd clobber the caret on every keystroke.
  useEffect(() => {
    if (!editor) return;
    const current = editor.getHTML();
    if (value !== current) {
      editor.commands.setContent(value || '', { emitUpdate: false });
    }
  }, [editor, value]);

  useEffect(() => {
    if (editor) editor.setEditable(!disabled);
  }, [editor, disabled]);

  return (
    <div
      className={cn(
        'rounded-lg ring-1 ring-inset ring-hairline transition-colors',
        'focus-within:ring-2 focus-within:ring-brand-blue',
        disabled && 'opacity-50',
      )}
    >
      <div className="flex items-center gap-0.5 border-b border-hairline px-1 py-1">
        <ToolbarButton
          label="Bold"
          icon={Bold}
          active={!!editor?.isActive('bold')}
          onClick={() => editor?.chain().focus().toggleBold().run()}
          disabled={disabled}
        />
        <ToolbarButton
          label="Italic"
          icon={Italic}
          active={!!editor?.isActive('italic')}
          onClick={() => editor?.chain().focus().toggleItalic().run()}
          disabled={disabled}
        />
        <div className="mx-1 h-4 w-px bg-hairline" aria-hidden />
        <ToolbarButton
          label="Bulleted list"
          icon={List}
          active={!!editor?.isActive('bulletList')}
          onClick={() => editor?.chain().focus().toggleBulletList().run()}
          disabled={disabled}
        />
        <ToolbarButton
          label="Numbered list"
          icon={ListOrdered}
          active={!!editor?.isActive('orderedList')}
          onClick={() => editor?.chain().focus().toggleOrderedList().run()}
          disabled={disabled}
        />
        <div className="ml-auto flex items-center gap-0.5">
          <ToolbarButton
            label="Undo"
            icon={Undo2}
            onClick={() => editor?.chain().focus().undo().run()}
            disabled={disabled || !editor?.can().undo()}
          />
          <ToolbarButton
            label="Redo"
            icon={Redo2}
            onClick={() => editor?.chain().focus().redo().run()}
            disabled={disabled || !editor?.can().redo()}
          />
        </div>
      </div>
      <EditorContent editor={editor} />
    </div>
  );
}

function ToolbarButton({
  label,
  icon: Icon,
  active,
  onClick,
  disabled,
}: {
  label: string;
  icon: typeof Bold;
  active?: boolean;
  onClick: () => void;
  disabled?: boolean;
}) {
  return (
    <button
      type="button"
      aria-label={label}
      aria-pressed={active}
      title={label}
      onClick={onClick}
      disabled={disabled}
      className={cn(
        'inline-flex size-7 items-center justify-center rounded-md text-fg-subtle transition-colors',
        'hover:bg-surface hover:text-fg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue',
        'disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent',
        active && 'bg-surface text-fg',
      )}
    >
      <Icon className="size-3.5" />
    </button>
  );
}
