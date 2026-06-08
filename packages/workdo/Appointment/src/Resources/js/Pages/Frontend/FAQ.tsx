import { useState } from 'react';
import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Calendar, ChevronDown, ChevronUp } from 'lucide-react';
import PublicLayout from './components/PublicLayout';

interface FAQProps {
    faqSettings?: {
        faq_title: string;
        faq_description: string;
        faq_questions: Array<{
            title: string;
            description: string;
        }>;
    };
}

export default function FAQ({ faqSettings }: FAQProps) {
    const { t } = useTranslation();
    const [openFaq, setOpenFaq] = useState<number | null>(0);

    const faqs = faqSettings?.faq_questions || [];

    const toggleFaq = (index: number) => {
        setOpenFaq(openFaq === index ? null : index);
    };

    return (
        <PublicLayout 
            title={faqSettings?.faq_title || t('Frequently Asked Questions')}
        >
            {/* Hero Section */}
            <section className="bg-gradient-to-br from-blue-50 to-indigo-100 py-20">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <div className="w-20 h-20 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg">
                        <span className="text-3xl">❓</span>
                    </div>
                    <h1 className="text-5xl font-bold text-gray-900 mb-6">
                        {faqSettings?.faq_title || t('Frequently Asked Questions')}
                    </h1>
                    <p className="text-xl text-gray-600 max-w-3xl mx-auto">
                        {faqSettings?.faq_description || t('Find answers to common questions about our appointment booking system and get the help you need.')}
                    </p>
                </div>
            </section>

            {/* FAQ Section */}
            <section className="py-16 bg-white">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="space-y-4">
                        {faqs.map((faq, index) => (
                            <div key={index} className="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden">
                                <button
                                    onClick={() => toggleFaq(index)}
                                    className="w-full px-8 py-6 text-left flex items-center justify-between hover:bg-gray-50 transition-all duration-200"
                                >
                                    <span className="text-lg font-semibold text-gray-900 pr-4">{faq.title}</span>
                                    <div className={`w-8 h-8 rounded-full flex items-center justify-center transition-all duration-200 ${
                                        openFaq === index ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-500'
                                    }`}>
                                        {openFaq === index ? (
                                            <ChevronUp className="h-4 w-4" />
                                        ) : (
                                            <ChevronDown className="h-4 w-4" />
                                        )}
                                    </div>
                                </button>
                                {openFaq === index && (
                                    <div className="px-8 pb-6 border-t border-gray-100">
                                        <div className="pt-4">
                                            <p className="text-gray-600 leading-relaxed text-base">{faq.description}</p>
                                        </div>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>


                </div>
            </section>
        </PublicLayout>
    );
}