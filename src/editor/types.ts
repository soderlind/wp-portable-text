declare global {
	interface Window {
		wpPortableText: {
			initialValue: PortableTextBlock[] | null;
			postId: number;
			restNonce: string;
			restUrl: string;
		};
		wp?: {
			media: (options: {
				title?: string;
				button?: { text?: string };
				multiple?: boolean;
				library?: { type?: string };
			}) => WpMediaFrame;
		};
	}
}

interface WpMediaFrame {
	on: (event: string, callback: () => void) => WpMediaFrame;
	open: () => WpMediaFrame;
	state: () => {
		get: (key: string) => {
			first: () => {
				toJSON: () => WpMediaAttachment;
			};
		};
	};
}

export interface WpMediaAttachment {
	id: number;
	url: string;
	alt: string;
	caption: string;
	title: string;
	width: number;
	height: number;
}

// Re-export the block type from the editor package.
import type { PortableTextBlock } from '@portabletext/editor';
export type { PortableTextBlock };

export {};
