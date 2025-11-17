// @/lib/mail-data.ts
export interface Mail {
    id: string;
    name: string;
    email: string;
    subject: string;
    preview: string;
    content: string;
    date: string;
    unread?: boolean;
    labels?: string[];
}

export const mails: Mail[] = [
    {
        id: '1',
        name: 'William Smith',
        email: 'williamsmith@example.com',
        subject: 'Meeting Tomorrow',
        preview:
            "Hi, let's have a meeting tomorrow to discuss the project. I've been reviewing the project details and have some ideas I'd like to share...",
        content:
            "Hi, let's have a meeting tomorrow to discuss the project. I've been reviewing the project details and have some ideas I'd like to share. It's crucial that we align on our next steps to ensure the project's success.\n\nPlease come prepared with any questions or insights you may have. Looking forward to our meeting!",
        date: 'Oct 22, 2023, 9:00:00 AM',
        unread: true,
        labels: ['meeting', 'work', 'important'],
    },
    // ...rest of your mails
];
