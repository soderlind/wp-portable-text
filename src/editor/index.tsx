import { useState, useCallback, useRef, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import {
	EditorProvider,
	PortableTextEditable,
	useEditor,
} from '@portabletext/editor';
import type {
	PortableTextBlock,
	RenderDecoratorFunction,
	RenderStyleFunction,
	RenderBlockFunction,
	RenderListItemFunction,
	RenderAnnotationFunction,
} from '@portabletext/editor';
import { EventListenerPlugin } from '@portabletext/editor/plugins';
import { schemaDefinition } from './schema';
import { Toolbar } from './components/Toolbar';
import { PreviewPanel } from './components/PreviewPanel';
import './styles.css';

/**
 * Editable image block rendered inside the editor.
 * Click opens an edit dialog for alt text / caption,
 * with a "Replace" button that opens the WP media modal.
 */
function ImageBlock(props: {
	path: [{ _key: string }];
	value: Record<string, unknown>;
}) {
	const editor = useEditor();
	const [dialogOpen, setDialogOpen] = useState(false);
	const [alt, setAlt] = useState('');
	const [caption, setCaption] = useState('');

	const src = (props.value.src as string) || '';
	const currentAlt = (props.value.alt as string) || '';
	const currentCaption = (props.value.caption as string) || '';

	const openDialog = () => {
		setAlt(currentAlt);
		setCaption(currentCaption);
		setDialogOpen(true);
	};

	const handleSave = (e: React.FormEvent) => {
		e.preventDefault();
		editor.send({
			type: 'block.set',
			at: props.path,
			props: { alt, caption },
		});
		setDialogOpen(false);
	};

	const handleReplace = () => {
		if (!window.wp?.media) {
			const newSrc = prompt('Image URL:', src);
			if (newSrc) {
				editor.send({
					type: 'block.set',
					at: props.path,
					props: { src: newSrc },
				});
			}
			return;
		}

		const frame = window.wp.media({
			title: 'Replace Image',
			button: { text: 'Replace Image' },
			multiple: false,
			library: { type: 'image' },
		});

		frame.on('select', () => {
			const attachment = frame.state().get('selection').first().toJSON();
			editor.send({
				type: 'block.set',
				at: props.path,
				props: {
					src: attachment.url,
					alt: attachment.alt || alt,
					caption: attachment.caption || caption,
					attachmentId: attachment.id,
				},
			});
			setAlt(attachment.alt || alt);
			setCaption(attachment.caption || caption);
		});

		frame.open();
	};

	return (
		<>
			<div
				className="wp-pt-block-object wp-pt-image"
				contentEditable={false}
				onClick={openDialog}
				role="button"
				tabIndex={0}
				title="Click to edit image"
			>
				{src ? (
					<img src={src} alt={currentAlt} />
				) : (
					<div className="wp-pt-image-placeholder">🖼 Image</div>
				)}
				{currentCaption && <figcaption>{currentCaption}</figcaption>}
				<div className="wp-pt-image-edit-hint">Click to edit</div>
			</div>

			{dialogOpen && (
				<div className="wp-pt-dialog-overlay" onClick={() => setDialogOpen(false)}>
					<div
						className="wp-pt-dialog"
						onClick={(e) => e.stopPropagation()}
						role="dialog"
						aria-label="Edit Image"
					>
						<div className="wp-pt-dialog-header">Edit Image</div>
						<form className="wp-pt-dialog-body" onSubmit={handleSave}>
							{src && (
								<div className="wp-pt-image-preview">
									<img src={src} alt={alt} />
								</div>
							)}
							<label className="wp-pt-dialog-label">
								Alt Text
								<input
									type="text"
									className="wp-pt-dialog-input"
									value={alt}
									onChange={(e) => setAlt(e.target.value)}
									placeholder="Describe the image"
									autoFocus
								/>
							</label>
							<label className="wp-pt-dialog-label">
								Caption
								<input
									type="text"
									className="wp-pt-dialog-input"
									value={caption}
									onChange={(e) => setCaption(e.target.value)}
									placeholder="Optional caption"
								/>
							</label>
							<div className="wp-pt-dialog-actions">
								<button
									type="button"
									className="wp-pt-dialog-btn wp-pt-dialog-btn--cancel"
									onClick={handleReplace}
								>
									Replace Image
								</button>
								<button
									type="button"
									className="wp-pt-dialog-btn wp-pt-dialog-btn--cancel"
									onClick={() => setDialogOpen(false)}
								>
									Cancel
								</button>
								<button type="submit" className="wp-pt-dialog-btn wp-pt-dialog-btn--primary">
									Save
								</button>
							</div>
						</form>
					</div>
				</div>
			)}
		</>
	);
}

const CODE_LANGUAGES = [
	'', 'bash', 'c', 'cpp', 'css', 'diff', 'go', 'graphql', 'html',
	'java', 'javascript', 'json', 'kotlin', 'lua', 'markdown',
	'php', 'python', 'ruby', 'rust', 'scss', 'shell', 'sql',
	'swift', 'text', 'typescript', 'xml', 'yaml',
];

/**
 * Editable code block rendered inside the editor.
 * Click opens a dialog to edit the code and language.
 */
function CodeBlock(props: {
	path: [{ _key: string }];
	value: Record<string, unknown>;
}) {
	const editor = useEditor();
	const [dialogOpen, setDialogOpen] = useState(false);
	const [codeValue, setCodeValue] = useState('');
	const [language, setLanguage] = useState('');
	const textareaRef = useRef<HTMLTextAreaElement>(null);

	const currentCode = (props.value.code as string) || '';
	const currentLanguage = (props.value.language as string) || '';

	const openDialog = () => {
		setCodeValue(currentCode);
		setLanguage(currentLanguage);
		setDialogOpen(true);
	};

	useEffect(() => {
		if (dialogOpen && textareaRef.current) {
			textareaRef.current.focus();
		}
	}, [dialogOpen]);

	const handleSave = (e: React.FormEvent) => {
		e.preventDefault();
		editor.send({
			type: 'block.set',
			at: props.path,
			props: { code: codeValue, language },
		});
		setDialogOpen(false);
	};

	const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
		if (e.key === 'Tab') {
			e.preventDefault();
			const el = e.currentTarget;
			const start = el.selectionStart;
			const end = el.selectionEnd;
			const val = el.value;
			setCodeValue(val.substring(0, start) + '\t' + val.substring(end));
			requestAnimationFrame(() => {
				el.selectionStart = el.selectionEnd = start + 1;
			});
		}
	};

	return (
		<>
			<div
				className="wp-pt-block-object wp-pt-code-block"
				contentEditable={false}
				onClick={openDialog}
				role="button"
				tabIndex={0}
				title="Click to edit code block"
			>
				<pre>
					<code>{currentCode || '// code'}</code>
				</pre>
				{currentLanguage && (
					<span className="wp-pt-code-lang">{currentLanguage}</span>
				)}
				<div className="wp-pt-code-edit-hint">Click to edit</div>
			</div>

			{dialogOpen && (
				<div className="wp-pt-dialog-overlay" onClick={() => setDialogOpen(false)}>
					<div
						className="wp-pt-dialog wp-pt-dialog--wide"
						onClick={(e) => e.stopPropagation()}
						role="dialog"
						aria-label="Edit Code Block"
					>
						<div className="wp-pt-dialog-header">Edit Code Block</div>
						<form className="wp-pt-dialog-body" onSubmit={handleSave}>
							<label className="wp-pt-dialog-label">
								Language
								<select
									className="wp-pt-dialog-select"
									value={language}
									onChange={(e) => setLanguage(e.target.value)}
								>
									{CODE_LANGUAGES.map((lang) => (
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
									Save
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
 * Main Portable Text Editor application for WordPress.
 */
function App() {
	const config = window.wpPortableText;
	const [value, setValue] = useState<PortableTextBlock[] | undefined>(
		config?.initialValue ?? undefined,
	);

	/**
	 * Sync editor value to the hidden textarea so WordPress
	 * receives the JSON content on form submit.
	 */
	const syncToTextarea = useCallback((newValue: PortableTextBlock[] | undefined) => {
		setValue(newValue);

		const textarea = document.getElementById(
			'wp-portable-text-content',
		) as HTMLTextAreaElement | null;

		if (textarea && newValue) {
			textarea.value = JSON.stringify(newValue);
		}
	}, []);

	// Render functions for PT editor elements.
	const renderStyle: RenderStyleFunction = (props) => {
		switch (props.schemaType.value) {
			case 'h1':
				return <h1>{props.children}</h1>;
			case 'h2':
				return <h2>{props.children}</h2>;
			case 'h3':
				return <h3>{props.children}</h3>;
			case 'h4':
				return <h4>{props.children}</h4>;
			case 'h5':
				return <h5>{props.children}</h5>;
			case 'h6':
				return <h6>{props.children}</h6>;
			case 'blockquote':
				return <blockquote>{props.children}</blockquote>;
			default:
				return <>{props.children}</>;
		}
	};

	const renderDecorator: RenderDecoratorFunction = (props) => {
		switch (props.value) {
			case 'strong':
				return <strong>{props.children}</strong>;
			case 'em':
				return <em>{props.children}</em>;
			case 'underline':
				return <u>{props.children}</u>;
			case 'strike-through':
				return <s>{props.children}</s>;
			case 'code':
				return <code className="wp-pt-inline-code">{props.children}</code>;
			case 'subscript':
				return <sub>{props.children}</sub>;
			case 'superscript':
				return <sup>{props.children}</sup>;
			default:
				return <>{props.children}</>;
		}
	};

	const renderAnnotation: RenderAnnotationFunction = (props) => {
		if (props.schemaType.name === 'link') {
			return (
				<span className="wp-pt-link" title={props.value.href}>
					{props.children}
				</span>
			);
		}
		return <>{props.children}</>;
	};

	const renderBlock: RenderBlockFunction = (props) => {
		const typeName = props.schemaType.name;

		if (typeName === 'break') {
			return (
				<div className="wp-pt-block-object wp-pt-break" contentEditable={false}>
					<hr />
				</div>
			);
		}
		if (typeName === 'image') {
			return <ImageBlock path={props.path} value={props.value} />;
		}
		if (typeName === 'codeBlock') {
			return <CodeBlock path={props.path} value={props.value} />;
		}
		if (typeName === 'embed') {
			return (
				<div className="wp-pt-block-object wp-pt-embed" contentEditable={false}>
					<span>▶ Embed: {props.value.url || 'No URL'}</span>
				</div>
			);
		}

		return <div className="wp-pt-block">{props.children}</div>;
	};

	const renderListItem: RenderListItemFunction = (props) => {
		return <li>{props.children}</li>;
	};

	return (
		<EditorProvider
			initialConfig={{
				schemaDefinition,
				initialValue: value,
			}}
		>
			<EventListenerPlugin
				on={(event) => {
					if (event.type === 'mutation') {
						syncToTextarea(event.value);
					}
				}}
			/>
			<div className="wp-portable-text-editor-container">
				<Toolbar />
				<PortableTextEditable
					className="wp-portable-text-editable"
					renderStyle={renderStyle}
					renderDecorator={renderDecorator}
					renderAnnotation={renderAnnotation}
					renderBlock={renderBlock}
					renderListItem={renderListItem}
				/>
			</div>
			<PreviewPanel value={value} />
		</EditorProvider>
	);
}

/**
 * Mount the editor when the DOM is ready.
 */
document.addEventListener('DOMContentLoaded', () => {
	const container = document.getElementById('wp-portable-text-editor');
	if (container) {
		const root = createRoot(container);
		root.render(<App />);
	}
});
