import { useState, useRef, useEffect, useCallback } from 'react';
import {
	useDecoratorButton,
	useStyleSelector,
	useListButton,
	useAnnotationButton,
	useAnnotationPopover,
	useBlockObjectButton,
	useToolbarSchema,
	type ExtendDecoratorSchemaType,
	type ExtendStyleSchemaType,
	type ExtendAnnotationSchemaType,
	type ExtendBlockObjectSchemaType,
	type ExtendListSchemaType,
	type ToolbarDecoratorSchemaType,
	type ToolbarStyleSchemaType,
	type ToolbarListSchemaType,
	type ToolbarAnnotationSchemaType,
	type ToolbarBlockObjectSchemaType,
} from '@portabletext/toolbar';
import { useEditor } from '@portabletext/editor';
import {
	bold,
	italic,
	underline,
	link,
	strikeThrough,
	code,
	h1,
	h2,
	h3,
	h4,
	h5,
	h6,
	blockquote,
	normal,
} from '@portabletext/keyboard-shortcuts';

/**
 * Toolbar for the Portable Text editor.
 *
 * Renders style dropdown, decorator buttons, annotation buttons,
 * list buttons, and block object insertion buttons.
 */
export function Toolbar() {
	const toolbarSchema = useToolbarSchema({
		extendDecorator,
		extendStyle,
		extendAnnotation,
		extendBlockObject,
		extendList,
	});

	return (
		<>
			<div className="wp-pt-toolbar" role="toolbar" aria-label="Formatting">
				{/* Style dropdown */}
				{toolbarSchema.styles && (
					<>
						<StyleDropdown schemaTypes={toolbarSchema.styles} />
						<div className="wp-pt-toolbar-separator" />
					</>
				)}

				{/* Decorator buttons */}
				{toolbarSchema.decorators && (
					<>
						<div className="wp-pt-toolbar-group">
							{toolbarSchema.decorators.map((d) => (
								<DecoratorButton key={d.name} schemaType={d} />
							))}
						</div>
						<div className="wp-pt-toolbar-separator" />
					</>
				)}

				{/* Annotation buttons (link) */}
				{toolbarSchema.annotations && (
					<>
						<div className="wp-pt-toolbar-group">
							{toolbarSchema.annotations.map((a) => (
								<AnnotationButton key={a.name} schemaType={a} />
							))}
						</div>
						<div className="wp-pt-toolbar-separator" />
					</>
				)}

				{/* List buttons */}
				{toolbarSchema.lists && (
					<>
						<div className="wp-pt-toolbar-group">
							{toolbarSchema.lists.map((l) => (
								<ListButton key={l.name} schemaType={l} />
							))}
						</div>
						<div className="wp-pt-toolbar-separator" />
					</>
				)}

				{/* Block object buttons */}
				{toolbarSchema.blockObjects && (
					<div className="wp-pt-toolbar-group">
						{toolbarSchema.blockObjects.map((b) => (
							<BlockObjectButton key={b.name} schemaType={b} />
						))}
					</div>
				)}
			</div>

			{/* Annotation popover for editing existing annotations */}
			{toolbarSchema.annotations && (
				<AnnotationPopover schemaTypes={toolbarSchema.annotations} />
			)}
		</>
	);
}

// --- Extend schema with labels, icons, and keyboard shortcuts ---

const styleConfig: Record<string, { title: string; shortcut?: ReturnType<typeof bold> }> = {
	normal: { title: 'Paragraph', shortcut: normal },
	h1: { title: 'Heading 1', shortcut: h1 },
	h2: { title: 'Heading 2', shortcut: h2 },
	h3: { title: 'Heading 3', shortcut: h3 },
	h4: { title: 'Heading 4', shortcut: h4 },
	h5: { title: 'Heading 5', shortcut: h5 },
	h6: { title: 'Heading 6', shortcut: h6 },
	blockquote: { title: 'Quote', shortcut: blockquote },
};

const extendStyle: ExtendStyleSchemaType = (style) => ({
	...style,
	...(styleConfig[style.name] ?? {}),
});

const decoratorConfig: Record<
	string,
	{
		icon: () => JSX.Element;
		shortcut?: ReturnType<typeof bold>;
		title: string;
		mutuallyExclusive?: string[];
	}
> = {
	strong: {
		icon: () => <strong>B</strong>,
		shortcut: bold,
		title: 'Bold',
	},
	em: {
		icon: () => <em>I</em>,
		shortcut: italic,
		title: 'Italic',
	},
	underline: {
		icon: () => <u>U</u>,
		shortcut: underline,
		title: 'Underline',
	},
	'strike-through': {
		icon: () => <s>S</s>,
		shortcut: strikeThrough,
		title: 'Strikethrough',
	},
	code: {
		icon: () => <code>&lt;/&gt;</code>,
		title: 'Code',
		shortcut: code,
	},
	subscript: {
		icon: () => (
			<span>
				X<sub>2</sub>
			</span>
		),
		title: 'Subscript',
		mutuallyExclusive: ['superscript'],
	},
	superscript: {
		icon: () => (
			<span>
				X<sup>2</sup>
			</span>
		),
		title: 'Superscript',
		mutuallyExclusive: ['subscript'],
	},
};

const extendDecorator: ExtendDecoratorSchemaType = (decorator) => {
	const config = decoratorConfig[decorator.name];
	if (config) {
		return { ...decorator, ...config };
	}
	return decorator;
};

const extendAnnotation: ExtendAnnotationSchemaType = (annotation) => {
	if (annotation.name === 'link') {
		return {
			...annotation,
			icon: () => <span>🔗</span>,
			title: 'Link',
			defaultValues: { href: 'https://' },
			shortcut: link,
		};
	}
	return annotation;
};

const extendBlockObject: ExtendBlockObjectSchemaType = (blockObject) => {
	if (blockObject.name === 'break') {
		return {
			...blockObject,
			icon: () => <span>―</span>,
			title: 'Separator',
		};
	}
	if (blockObject.name === 'image') {
		return {
			...blockObject,
			icon: () => <span>🖼</span>,
			title: 'Image',
		};
	}
	if (blockObject.name === 'codeBlock') {
		return {
			...blockObject,
			icon: () => <span>{ }</span>,
			title: 'Code Block',
		};
	}
	if (blockObject.name === 'embed') {
		return {
			...blockObject,
			icon: () => <span>▶</span>,
			title: 'Embed',
		};
	}
	return blockObject;
};

const extendList: ExtendListSchemaType = (list) => {
	if (list.name === 'bullet') {
		return { ...list, icon: () => <span>•</span>, title: 'Bullet List' };
	}
	if (list.name === 'number') {
		return { ...list, icon: () => <span>1.</span>, title: 'Numbered List' };
	}
	return list;
};

// --- Button components ---

function DecoratorButton(props: { schemaType: ToolbarDecoratorSchemaType }) {
	const decoratorButton = useDecoratorButton(props);
	const isActive = decoratorButton.snapshot.matches({ enabled: 'active' });
	const isDisabled = decoratorButton.snapshot.matches('disabled');

	return (
		<button
			type="button"
			onClick={() => decoratorButton.send({ type: 'toggle' })}
			className={`wp-pt-toolbar-btn ${isActive ? 'is-active' : ''}`}
			title={props.schemaType.title || props.schemaType.name}
			disabled={isDisabled}
		>
			{props.schemaType.icon && <props.schemaType.icon />}
		</button>
	);
}

/**
 * Style dropdown — replaces individual style buttons with a <select>.
 */
function StyleDropdown(props: { schemaTypes: ReadonlyArray<ToolbarStyleSchemaType> }) {
	const styleSelector = useStyleSelector(props);
	const activeStyle = styleSelector.snapshot.context.activeStyle ?? 'normal';
	const isDisabled = styleSelector.snapshot.matches('disabled');

	return (
		<select
			className="wp-pt-toolbar-select"
			value={activeStyle}
			disabled={isDisabled}
			onChange={(e) => {
				styleSelector.send({ type: 'toggle', style: e.target.value });
			}}
			aria-label="Block style"
		>
			{props.schemaTypes.map((s) => (
				<option key={s.name} value={s.name}>
					{s.title || s.name}
				</option>
			))}
		</select>
	);
}

function ListButton(props: { schemaType: ToolbarListSchemaType }) {
	const listButton = useListButton(props);
	const isActive = listButton.snapshot.matches({ enabled: 'active' });
	const isDisabled = listButton.snapshot.matches('disabled');

	return (
		<button
			type="button"
			onClick={() => listButton.send({ type: 'toggle' })}
			className={`wp-pt-toolbar-btn ${isActive ? 'is-active' : ''}`}
			title={props.schemaType.title || props.schemaType.name}
			disabled={isDisabled}
		>
			{props.schemaType.icon ? <props.schemaType.icon /> : props.schemaType.title}
		</button>
	);
}

/**
 * Annotation button — toggle for adding/removing annotations (e.g. links).
 * When inactive, opens a dialog to enter annotation values.
 * When active, removes the annotation.
 */
function AnnotationButton(props: { schemaType: ToolbarAnnotationSchemaType }) {
	const annotationButton = useAnnotationButton(props);
	const [dialogOpen, setDialogOpen] = useState(false);
	const [href, setHref] = useState('https://');
	const inputRef = useRef<HTMLInputElement>(null);

	const isActive =
		annotationButton.snapshot.matches({ disabled: 'active' }) ||
		annotationButton.snapshot.matches({ enabled: 'active' });
	const isDisabled = annotationButton.snapshot.matches('disabled');

	// Focus input when dialog opens.
	useEffect(() => {
		if (dialogOpen && inputRef.current) {
			inputRef.current.focus();
			inputRef.current.select();
		}
	}, [dialogOpen]);

	if (isActive) {
		return (
			<button
				type="button"
				className="wp-pt-toolbar-btn is-active"
				title={`Remove ${props.schemaType.title ?? props.schemaType.name}`}
				disabled={annotationButton.snapshot.matches('disabled')}
				onClick={() => annotationButton.send({ type: 'remove' })}
			>
				{props.schemaType.icon ? <props.schemaType.icon /> : props.schemaType.title}
			</button>
		);
	}

	return (
		<>
			<button
				type="button"
				className="wp-pt-toolbar-btn"
				title={`Add ${props.schemaType.title ?? props.schemaType.name}`}
				disabled={isDisabled}
				onClick={() => {
					setHref('https://');
					setDialogOpen(true);
				}}
			>
				{props.schemaType.icon ? <props.schemaType.icon /> : props.schemaType.title}
			</button>
			{dialogOpen && (
				<div className="wp-pt-dialog-overlay" onClick={() => setDialogOpen(false)}>
					<div
						className="wp-pt-dialog"
						onClick={(e) => e.stopPropagation()}
						role="dialog"
						aria-label={`Add ${props.schemaType.title}`}
					>
						<div className="wp-pt-dialog-header">
							Add {props.schemaType.title ?? props.schemaType.name}
						</div>
						<form
							className="wp-pt-dialog-body"
							onSubmit={(e) => {
								e.preventDefault();
								annotationButton.send({
									type: 'add',
									annotation: { value: { href } },
								});
								setDialogOpen(false);
							}}
						>
							<label className="wp-pt-dialog-label">
								URL
								<input
									ref={inputRef}
									type="url"
									className="wp-pt-dialog-input"
									value={href}
									onChange={(e) => setHref(e.target.value)}
									placeholder="https://example.com"
									required
								/>
							</label>
							<div className="wp-pt-dialog-actions">
								<button
									type="button"
									className="wp-pt-dialog-btn wp-pt-dialog-btn--cancel"
									onClick={() => setDialogOpen(false)}
								>
									Cancel
								</button>
								<button type="submit" className="wp-pt-dialog-btn wp-pt-dialog-btn--primary">
									Add Link
								</button>
							</div>
						</form>
					</div>
				</div>
			)}
		</>
	);
}

/**
 * Popover that appears when cursor is inside an existing annotation.
 * Allows editing or removing the annotation.
 */
function AnnotationPopover(props: {
	schemaTypes: ReadonlyArray<ToolbarAnnotationSchemaType>;
}) {
	const annotationPopover = useAnnotationPopover(props);
	const [editingKey, setEditingKey] = useState<string | null>(null);
	const [editHref, setEditHref] = useState('');

	if (
		annotationPopover.snapshot.matches('disabled') ||
		annotationPopover.snapshot.matches({ enabled: 'inactive' })
	) {
		return null;
	}

	const annotations = annotationPopover.snapshot.context.annotations;
	const elementRef = annotationPopover.snapshot.context.elementRef;

	return (
		<AnnotationPopoverPortal elementRef={elementRef}>
			{annotations.map((annotation) => (
				<div key={annotation.value._key} className="wp-pt-annotation-popover-item">
					{editingKey === annotation.value._key ? (
						<form
							className="wp-pt-annotation-popover-edit"
							onSubmit={(e) => {
								e.preventDefault();
								annotationPopover.send({
									type: 'edit',
									at: annotation.at,
									props: { href: editHref },
								});
								setEditingKey(null);
							}}
						>
							<input
								type="url"
								className="wp-pt-dialog-input"
								value={editHref}
								onChange={(e) => setEditHref(e.target.value)}
								autoFocus
							/>
							<button type="submit" className="wp-pt-toolbar-btn" title="Save">
								✓
							</button>
							<button
								type="button"
								className="wp-pt-toolbar-btn"
								title="Cancel"
								onClick={() => setEditingKey(null)}
							>
								✕
							</button>
						</form>
					) : (
						<>
							<a
								className="wp-pt-annotation-popover-link"
								href={annotation.value.href}
								target="_blank"
								rel="noopener noreferrer"
								title={annotation.value.href}
							>
								{annotation.value.href}
							</a>
							<button
								type="button"
								className="wp-pt-toolbar-btn wp-pt-toolbar-btn--sm"
								title="Edit"
								onClick={() => {
									setEditHref(annotation.value.href ?? '');
									setEditingKey(annotation.value._key);
								}}
							>
								✎
							</button>
							<button
								type="button"
								className="wp-pt-toolbar-btn wp-pt-toolbar-btn--sm wp-pt-toolbar-btn--danger"
								title="Remove"
								onClick={() => {
									annotationPopover.send({
										type: 'remove',
										schemaType: annotation.schemaType,
									});
								}}
							>
								✕
							</button>
						</>
					)}
				</div>
			))}
		</AnnotationPopoverPortal>
	);
}

/**
 * Positions the annotation popover relative to the annotated element.
 */
function AnnotationPopoverPortal(props: {
	elementRef: React.RefObject<Element | null>;
	children: React.ReactNode;
}) {
	const popoverRef = useRef<HTMLDivElement>(null);

	useEffect(() => {
		const el = props.elementRef?.current;
		const popover = popoverRef.current;
		if (!el || !popover) return;

		const rect = el.getBoundingClientRect();
		const container = popover.offsetParent as HTMLElement | null;
		const containerRect = container?.getBoundingClientRect() ?? { left: 0, top: 0 };

		popover.style.left = `${rect.left - containerRect.left}px`;
		popover.style.top = `${rect.bottom - containerRect.top + 4}px`;
	});

	return (
		<div ref={popoverRef} className="wp-pt-annotation-popover">
			{props.children}
		</div>
	);
}

/**
 * Block object button — inserts a block object (image, separator, code block, embed).
 * Routes to specialized buttons for image and codeBlock, generic dialog for others.
 */
function BlockObjectButton(props: { schemaType: ToolbarBlockObjectSchemaType }) {
	if (props.schemaType.name === 'image') {
		return <ImageBlockButton schemaType={props.schemaType} />;
	}
	if (props.schemaType.name === 'codeBlock') {
		return <CodeBlockButton schemaType={props.schemaType} />;
	}
	return <GenericBlockObjectButton schemaType={props.schemaType} />;
}

/**
 * Image button — opens the WordPress media modal.
 * Uses editor.send() directly instead of useBlockObjectButton because
 * the WP media frame runs outside React and the editor loses focus,
 * which would cause the toolbar button state machine to go to 'disabled'.
 */
function ImageBlockButton(props: { schemaType: ToolbarBlockObjectSchemaType }) {
	const editor = useEditor();

	const handleClick = useCallback(() => {
		if (!window.wp?.media) {
			const src = prompt('Image URL:');
			if (src) {
				editor.send({
					type: 'insert.block object',
					blockObject: {
						name: 'image',
						value: { src, alt: '', caption: '' },
					},
					placement: 'auto',
				});
			}
			return;
		}

		const frame = window.wp.media({
			title: 'Select Image',
			button: { text: 'Insert Image' },
			multiple: false,
			library: { type: 'image' },
		});

		frame.on('select', () => {
			const attachment = frame.state().get('selection').first().toJSON();
			editor.send({
				type: 'insert.block object',
				blockObject: {
					name: 'image',
					value: {
						src: attachment.url,
						alt: attachment.alt || '',
						caption: attachment.caption || '',
						attachmentId: attachment.id,
					},
				},
				placement: 'auto',
			});
		});

		frame.open();
	}, [editor]);

	return (
		<button
			type="button"
			className="wp-pt-toolbar-btn"
			title="Insert Image"
			onClick={handleClick}
		>
			{props.schemaType.icon ? <props.schemaType.icon /> : null}
			<span className="wp-pt-toolbar-btn-label">Image</span>
		</button>
	);
}

const COMMON_LANGUAGES = [
	'', 'bash', 'c', 'cpp', 'css', 'diff', 'go', 'graphql', 'html',
	'java', 'javascript', 'json', 'kotlin', 'lua', 'markdown',
	'php', 'python', 'ruby', 'rust', 'scss', 'shell', 'sql',
	'swift', 'text', 'typescript', 'xml', 'yaml',
];

/**
 * Code Block button — opens a dialog with a textarea for code and a language selector.
 * Uses editor.send() directly instead of useBlockObjectButton because
 * the dialog steals focus from the editor, which causes the toolbar
 * state machine to go to 'disabled' before the insert callback fires.
 */
function CodeBlockButton(props: { schemaType: ToolbarBlockObjectSchemaType }) {
	const editor = useEditor();
	const [dialogOpen, setDialogOpen] = useState(false);
	const [codeValue, setCodeValue] = useState('');
	const [language, setLanguage] = useState('');
	const textareaRef = useRef<HTMLTextAreaElement>(null);

	useEffect(() => {
		if (dialogOpen && textareaRef.current) {
			textareaRef.current.focus();
		}
	}, [dialogOpen]);

	const handleOpen = () => {
		setCodeValue('');
		setLanguage('');
		setDialogOpen(true);
	};

	const handleSubmit = (e: React.FormEvent) => {
		e.preventDefault();
		editor.send({
			type: 'insert.block object',
			blockObject: {
				name: 'codeBlock',
				value: { code: codeValue, language },
			},
			placement: 'auto',
		});
		setDialogOpen(false);
	};

	// Handle Tab key inside textarea to insert a tab character.
	const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
		if (e.key === 'Tab') {
			e.preventDefault();
			const el = e.currentTarget;
			const start = el.selectionStart;
			const end = el.selectionEnd;
			const val = el.value;
			setCodeValue(val.substring(0, start) + '\t' + val.substring(end));
			// Restore cursor position after React re-render.
			requestAnimationFrame(() => {
				el.selectionStart = el.selectionEnd = start + 1;
			});
		}
	};

	return (
		<>
			<button
				type="button"
				className="wp-pt-toolbar-btn"
				title="Insert Code Block"
				onClick={handleOpen}
			>
				{props.schemaType.icon ? <props.schemaType.icon /> : null}
				<span className="wp-pt-toolbar-btn-label">Code Block</span>
			</button>

			{dialogOpen && (
				<div className="wp-pt-dialog-overlay" onClick={() => setDialogOpen(false)}>
					<div
						className="wp-pt-dialog wp-pt-dialog--wide"
						onClick={(e) => e.stopPropagation()}
						role="dialog"
						aria-label="Insert Code Block"
					>
						<div className="wp-pt-dialog-header">Insert Code Block</div>
						<form className="wp-pt-dialog-body" onSubmit={handleSubmit}>
							<label className="wp-pt-dialog-label">
								Language
								<select
									className="wp-pt-dialog-select"
									value={language}
									onChange={(e) => setLanguage(e.target.value)}
								>
									{COMMON_LANGUAGES.map((lang) => (
										<option key={lang} value={lang}>
											{lang || '(none)'}
										</option>
									))}
								</select>
							</label>
							<label className="wp-pt-dialog-label">
								Code
								<textarea
									ref={textareaRef}
									className="wp-pt-dialog-code"
									value={codeValue}
									onChange={(e) => setCodeValue(e.target.value)}
									onKeyDown={handleKeyDown}
									rows={12}
									spellCheck={false}
									placeholder="Paste or type your code here…"
								/>
							</label>
							<div className="wp-pt-dialog-actions">
								<button
									type="button"
									className="wp-pt-dialog-btn wp-pt-dialog-btn--cancel"
									onClick={() => setDialogOpen(false)}
								>
									Cancel
								</button>
								<button type="submit" className="wp-pt-dialog-btn wp-pt-dialog-btn--primary">
									Insert
								</button>
							</div>
						</form>
					</div>
				</div>
			)}
		</>
	);
}

/**
 * Generic block object button for break, embed, etc.
 */
function GenericBlockObjectButton(props: { schemaType: ToolbarBlockObjectSchemaType }) {
	const { snapshot, send } = useBlockObjectButton(props);
	const [dialogOpen, setDialogOpen] = useState(false);
	const [formValues, setFormValues] = useState<Record<string, string>>({});
	const isDisabled = snapshot.matches('disabled');
	const hasFields = props.schemaType.fields && props.schemaType.fields.length > 0;

	const handleInsert = () => {
		if (!hasFields) {
			send({ type: 'insert', value: {}, placement: 'auto' });
			return;
		}
		const defaults: Record<string, string> = {};
		for (const field of props.schemaType.fields ?? []) {
			defaults[field.name] = (props.schemaType.defaultValues?.[field.name] as string) ?? '';
		}
		setFormValues(defaults);
		setDialogOpen(true);
	};

	return (
		<>
			<button
				type="button"
				className="wp-pt-toolbar-btn"
				title={`Insert ${props.schemaType.title ?? props.schemaType.name}`}
				disabled={isDisabled}
				onClick={handleInsert}
			>
				{props.schemaType.icon ? <props.schemaType.icon /> : null}
				<span className="wp-pt-toolbar-btn-label">
					{props.schemaType.title ?? props.schemaType.name}
				</span>
			</button>

			{dialogOpen && (
				<div className="wp-pt-dialog-overlay" onClick={() => setDialogOpen(false)}>
					<div
						className="wp-pt-dialog"
						onClick={(e) => e.stopPropagation()}
						role="dialog"
						aria-label={`Insert ${props.schemaType.title}`}
					>
						<div className="wp-pt-dialog-header">
							Insert {props.schemaType.title ?? props.schemaType.name}
						</div>
						<form
							className="wp-pt-dialog-body"
							onSubmit={(e) => {
								e.preventDefault();
								send({ type: 'insert', value: formValues, placement: 'auto' });
								setDialogOpen(false);
							}}
						>
							{props.schemaType.fields?.map((field) => (
								<label key={field.name} className="wp-pt-dialog-label">
									{field.name}
									<input
										type="text"
										className="wp-pt-dialog-input"
										value={formValues[field.name] ?? ''}
										onChange={(e) =>
											setFormValues((prev) => ({
												...prev,
												[field.name]: e.target.value,
											}))
										}
									/>
								</label>
							))}
							<div className="wp-pt-dialog-actions">
								<button
									type="button"
									className="wp-pt-dialog-btn wp-pt-dialog-btn--cancel"
									onClick={() => setDialogOpen(false)}
								>
									Cancel
								</button>
								<button type="submit" className="wp-pt-dialog-btn wp-pt-dialog-btn--primary">
									Insert
								</button>
							</div>
						</form>
					</div>
				</div>
			)}
		</>
	);
}
