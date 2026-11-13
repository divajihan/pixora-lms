import { EditorContent, useEditor, useEditorState } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import type { LucideIcon } from 'lucide-react';
import {
    Bold,
    Code,
    Heading2,
    Heading3,
    Italic,
    Link as LinkIcon,
    List,
    ListOrdered,
    Quote,
    Redo,
    Strikethrough,
    Underline,
    Undo,
} from 'lucide-react';
import { useEffect } from 'react';
import { Toggle } from '@/components/ui/toggle';
import { cn } from '@/lib/utils';

/**
 * Rich text editor for lesson summaries. The toolbar only offers formatting
 * that the server allowlist keeps (config/purify.php "rich_text"); the HTML is
 * sanitised again on the server before saving.
 */
export function RichTextEditor({
    id,
    value,
    onChange,
    invalid = false,
    placeholder,
}: {
    id?: string;
    value: string;
    onChange: (html: string) => void;
    invalid?: boolean;
    placeholder?: string;
}) {
    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3] },
                link: {
                    openOnClick: false,
                    autolink: true,
                    protocols: ['http', 'https', 'mailto'],
                },
            }),
        ],
        content: value,
        immediatelyRender: false,
        editorProps: {
            attributes: {
                ...(id ? { id } : {}),
                role: 'textbox',
                'aria-multiline': 'true',
                'aria-label': placeholder ?? 'Ringkasan',
                class: 'rich-text min-h-40 px-3 py-2 outline-none',
            },
        },
        onUpdate: ({ editor: current }) => {
            onChange(current.isEmpty ? '' : current.getHTML());
        },
    });

    // Sync external resets (e.g. opening the dialog for another lesson).
    useEffect(() => {
        if (editor && !editor.isFocused && editor.getHTML() !== value) {
            editor.commands.setContent(value || '', { emitUpdate: false });
        }
    }, [editor, value]);

    const state = useEditorState({
        editor,
        selector: ({ editor: current }) => ({
            bold: current?.isActive('bold') ?? false,
            italic: current?.isActive('italic') ?? false,
            underline: current?.isActive('underline') ?? false,
            strike: current?.isActive('strike') ?? false,
            code: current?.isActive('code') ?? false,
            h2: current?.isActive('heading', { level: 2 }) ?? false,
            h3: current?.isActive('heading', { level: 3 }) ?? false,
            bullet: current?.isActive('bulletList') ?? false,
            ordered: current?.isActive('orderedList') ?? false,
            quote: current?.isActive('blockquote') ?? false,
            link: current?.isActive('link') ?? false,
        }),
    });

    const setLink = () => {
        if (!editor) {
            return;
        }

        const previous = editor.getAttributes('link').href as
            | string
            | undefined;
        const url = window.prompt(
            'Alamat tautan (https://…)',
            previous ?? 'https://',
        );

        if (url === null) {
            return;
        }

        if (url.trim() === '') {
            editor.chain().focus().extendMarkRange('link').unsetLink().run();

            return;
        }

        if (!/^(https?:\/\/|mailto:)/i.test(url.trim())) {
            window.alert(
                'Tautan harus diawali https://, http://, atau mailto:',
            );

            return;
        }

        editor
            .chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ href: url.trim() })
            .run();
    };

    const tools: {
        label: string;
        icon: LucideIcon;
        active?: boolean;
        run: () => void;
    }[] = [
        {
            label: 'Tebal',
            icon: Bold,
            active: state?.bold,
            run: () => editor?.chain().focus().toggleBold().run(),
        },
        {
            label: 'Miring',
            icon: Italic,
            active: state?.italic,
            run: () => editor?.chain().focus().toggleItalic().run(),
        },
        {
            label: 'Garis bawah',
            icon: Underline,
            active: state?.underline,
            run: () => editor?.chain().focus().toggleUnderline().run(),
        },
        {
            label: 'Coret',
            icon: Strikethrough,
            active: state?.strike,
            run: () => editor?.chain().focus().toggleStrike().run(),
        },
        {
            label: 'Judul 2',
            icon: Heading2,
            active: state?.h2,
            run: () =>
                editor?.chain().focus().toggleHeading({ level: 2 }).run(),
        },
        {
            label: 'Judul 3',
            icon: Heading3,
            active: state?.h3,
            run: () =>
                editor?.chain().focus().toggleHeading({ level: 3 }).run(),
        },
        {
            label: 'Daftar berpoin',
            icon: List,
            active: state?.bullet,
            run: () => editor?.chain().focus().toggleBulletList().run(),
        },
        {
            label: 'Daftar bernomor',
            icon: ListOrdered,
            active: state?.ordered,
            run: () => editor?.chain().focus().toggleOrderedList().run(),
        },
        {
            label: 'Kutipan',
            icon: Quote,
            active: state?.quote,
            run: () => editor?.chain().focus().toggleBlockquote().run(),
        },
        {
            label: 'Kode',
            icon: Code,
            active: state?.code,
            run: () => editor?.chain().focus().toggleCode().run(),
        },
        { label: 'Tautan', icon: LinkIcon, active: state?.link, run: setLink },
    ];

    return (
        <div
            className={cn(
                'overflow-hidden rounded-md border border-input bg-background shadow-xs focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50',
                invalid && 'border-destructive',
            )}
        >
            <div
                role="toolbar"
                aria-label="Format teks"
                className="flex flex-wrap items-center gap-0.5 border-b bg-muted/40 p-1"
            >
                {tools.map((tool) => (
                    <Toggle
                        key={tool.label}
                        size="sm"
                        pressed={tool.active ?? false}
                        onPressedChange={() => tool.run()}
                        aria-label={tool.label}
                        title={tool.label}
                        disabled={!editor}
                    >
                        <tool.icon className="size-4" />
                    </Toggle>
                ))}
                <span className="mx-1 h-5 w-px bg-border" aria-hidden />
                <Toggle
                    size="sm"
                    pressed={false}
                    onPressedChange={() => editor?.chain().focus().undo().run()}
                    aria-label="Urungkan"
                    title="Urungkan"
                    disabled={!editor}
                >
                    <Undo className="size-4" />
                </Toggle>
                <Toggle
                    size="sm"
                    pressed={false}
                    onPressedChange={() => editor?.chain().focus().redo().run()}
                    aria-label="Ulangi"
                    title="Ulangi"
                    disabled={!editor}
                >
                    <Redo className="size-4" />
                </Toggle>
            </div>
            {editor ? (
                <EditorContent editor={editor} />
            ) : (
                <div className="min-h-40 px-3 py-2 text-sm text-muted-foreground">
                    Memuat editor…
                </div>
            )}
        </div>
    );
}
