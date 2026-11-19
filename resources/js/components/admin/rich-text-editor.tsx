import Image from '@tiptap/extension-image';
import { EditorContent, useEditor, useEditorState } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import type { LucideIcon } from 'lucide-react';
import {
    Bold,
    Code,
    Heading2,
    Heading3,
    ImagePlus,
    Italic,
    Loader2,
    Link as LinkIcon,
    List,
    ListOrdered,
    Quote,
    Redo,
    Strikethrough,
    Underline,
    Undo,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ChangeEvent } from 'react';
import { toast } from 'sonner';
import { Toggle } from '@/components/ui/toggle';
import { cn } from '@/lib/utils';
import { store as storeContentImage } from '@/routes/admin/content-images';

function csrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/** Uploads one image (re-encoded on the server) and returns its URL. */
async function uploadImage(file: File): Promise<string> {
    const body = new FormData();
    body.append('image', file);

    const response = await fetch(storeContentImage().url, {
        method: 'POST',
        body,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-XSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    const data = (await response.json().catch(() => ({}))) as {
        url?: string;
        message?: string;
        errors?: Record<string, string[]>;
    };

    if (!response.ok || !data.url) {
        throw new Error(
            data.errors?.image?.[0] ??
                (response.status === 413
                    ? 'Ukuran gambar terlalu besar.'
                    : (data.message ?? 'Gambar gagal diunggah.')),
        );
    }

    return data.url;
}

/**
 * Rich text editor for lesson notes and assignment instructions. The toolbar
 * only offers formatting that the server allowlist keeps (config/purify.php
 * "rich_text"); the HTML is sanitised again on the server before saving.
 * With `images`, a toolbar button uploads example images (JPEG/PNG/WebP,
 * re-encoded on the server); only those uploads survive sanitising.
 */
export function RichTextEditor({
    id,
    value,
    onChange,
    invalid = false,
    placeholder,
    images = false,
}: {
    id?: string;
    value: string;
    onChange: (html: string) => void;
    invalid?: boolean;
    placeholder?: string;
    images?: boolean;
}) {
    const fileInput = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);

    const editor = useEditor({
        extensions: [
            Image.configure({ inline: false, allowBase64: false }),
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
                'aria-label': placeholder ?? 'Catatan',
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

    const onFiles = async (event: ChangeEvent<HTMLInputElement>) => {
        const files = Array.from(event.target.files ?? []);
        event.target.value = '';

        if (!editor || files.length === 0) {
            return;
        }

        setUploading(true);

        try {
            for (const file of files) {
                const src = await uploadImage(file);
                editor
                    .chain()
                    .focus()
                    .setImage({ src, alt: file.name.replace(/\.[^.]+$/, '') })
                    .run();
            }
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Gambar gagal diunggah.',
            );
        } finally {
            setUploading(false);
        }
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
                {images && (
                    <>
                        <Toggle
                            size="sm"
                            pressed={false}
                            onPressedChange={() => fileInput.current?.click()}
                            aria-label="Sisipkan gambar"
                            title="Sisipkan gambar (JPEG, PNG, WebP)"
                            disabled={!editor || uploading}
                        >
                            {uploading ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <ImagePlus className="size-4" />
                            )}
                        </Toggle>
                        <input
                            ref={fileInput}
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            multiple
                            hidden
                            onChange={onFiles}
                        />
                    </>
                )}
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
