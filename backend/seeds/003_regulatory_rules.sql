-- Seed 003: EU AI Act regulatory rules
-- Initial regulatory rules and requirements for compliance tracking

INSERT INTO regulatory_rule_versions (
    rule_code, version, title, description, regulatory_text, regulation_type, severity_level,
    article_reference, applies_to_risk_categories, applies_to_system_types, effective_from,
    effective_until, is_current, source_url, interpretation_notes
) VALUES

-- High-Risk AI Systems - Article 6
(
    'EU_AI_ACT_ART_6',
    'v1.0',
    'Classification of AI systems as high-risk',
    'AI systems shall be considered high-risk where they are intended to be used in specific areas and fulfill specific purposes as listed in Annex III',
    'AI systems shall be considered high-risk AI systems where both of the following conditions are fulfilled: (a) the AI system is intended to be used as a safety component of a product, or the AI system is itself a product, covered by the Union harmonisation legislation listed in Annex I; (b) the product whose safety component pursuant to point (a) is the AI system, or the AI system itself as a product, is required to undergo a third-party conformity assessment with a view to the placing on the market or putting into service of that product pursuant to the Union harmonisation legislation listed in Annex I.',
    'eu_ai_act',
    'high',
    'Article 6',
    JSON_ARRAY('high_risk'),
    JSON_ARRAY('safety_component', 'standalone_system'),
    '2024-08-01',
    NULL,
    TRUE,
    'https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=celex%3A32024R1689',
    'Organizations must assess whether their AI systems fall under high-risk categories defined in Annex III and implement appropriate risk management systems.'
),

-- Risk Management - Article 9
(
    'EU_AI_ACT_ART_9',
    'v1.0',
    'Risk management system for high-risk AI systems',
    'A risk management system shall be established, implemented, documented and maintained in relation to high-risk AI systems',
    'A risk management system shall be established, implemented, documented and maintained in relation to high-risk AI systems. The risk management system shall be a continuous iterative process planned and run throughout the entire lifecycle of a high-risk AI system, requiring regular systematic updating.',
    'eu_ai_act',
    'high',
    'Article 9',
    JSON_ARRAY('high_risk'),
    JSON_ARRAY('high_risk_system'),
    '2024-08-01',
    NULL,
    TRUE,
    'https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=celex%3A32024R1689',
    'Risk management must be ongoing throughout the AI system lifecycle, not a one-time assessment.'
),

-- Data Governance - Article 10
(
    'EU_AI_ACT_ART_10',
    'v1.0',
    'Data and data governance for high-risk AI systems',
    'High-risk AI systems shall be developed on the basis of training, validation and testing datasets that meet specific quality requirements',
    'High-risk AI systems which make use of techniques involving the training of models with data shall be developed on the basis of training, validation and testing datasets that meet the quality criteria referred to in paragraphs 2 to 5.',
    'eu_ai_act',
    'high',
    'Article 10',
    JSON_ARRAY('high_risk'),
    JSON_ARRAY('ml_system', 'deep_learning'),
    '2024-08-01',
    NULL,
    TRUE,
    'https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=celex%3A32024R1689',
    'Applies specifically to AI systems that use training data. Quality requirements include representativeness, relevance, and accuracy.'
),

-- Technical Documentation - Article 11
(
    'EU_AI_ACT_ART_11',
    'v1.0',
    'Technical documentation for high-risk AI systems',
    'Technical documentation shall be drawn up before the high-risk AI system is placed on the market or put into service',
    'Technical documentation shall be drawn up before the high-risk AI system is placed on the market or put into service and shall be kept up-to date. The technical documentation shall be drawn up in such a way as to demonstrate that the high-risk AI system complies with the requirements set out in this Section and provide national competent authorities and notified bodies with all the information necessary to assess the compliance of the AI system.',
    'eu_ai_act',
    'high',
    'Article 11',
    JSON_ARRAY('high_risk'),
    JSON_ARRAY('high_risk_system'),
    '2024-08-01',
    NULL,
    TRUE,
    'https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=celex%3A32024R1689',
    'Documentation must be prepared BEFORE market placement and kept current throughout the system lifecycle.'
),

-- Record-keeping - Article 12
(
    'EU_AI_ACT_ART_12',
    'v1.0',
    'Record-keeping obligations for high-risk AI systems',
    'High-risk AI systems shall be designed and developed with capabilities enabling the automatic recording of events',
    'High-risk AI systems shall be designed and developed with capabilities enabling the automatic recording of events (logs) over the duration of the lifetime of the system.',
    'eu_ai_act',
    'high',
    'Article 12',
    JSON_ARRAY('high_risk'),
    JSON_ARRAY('high_risk_system'),
    '2024-08-01',
    NULL,
    TRUE,
    'https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=celex%3A32024R1689',
    'Automatic logging must be designed into the system architecture, not added as an afterthought.'
),

-- Transparency and User Information - Article 13
(
    'EU_AI_ACT_ART_13',
    'v1.0',
    'Transparency and provision of information to deployers',
    'High-risk AI systems shall be designed and developed in such a way as to ensure that their operation is sufficiently transparent',
    'High-risk AI systems shall be designed and developed in such a way as to ensure that their operation is sufficiently transparent to enable deployers to interpret the system\'s output and use it appropriately.',
    'eu_ai_act',
    'medium',
    'Article 13',
    JSON_ARRAY('high_risk'),
    JSON_ARRAY('high_risk_system'),
    '2024-08-01',
    NULL,
    TRUE,
    'https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=celex%3A32024R1689',
    'Transparency requirements focus on enabling deployers to understand and appropriately use AI system outputs.'
),

-- Human Oversight - Article 14
(
    'EU_AI_ACT_ART_14',
    'v1.0',
    'Human oversight of high-risk AI systems',
    'High-risk AI systems shall be designed and developed in such a way as to ensure effective oversight by natural persons',
    'High-risk AI systems shall be designed and developed in such a way, including with appropriate human-machine interface tools, as to ensure that they can be effectively overseen by natural persons during the period in which the AI system is in use.',
    'eu_ai_act',
    'high',
    'Article 14',
    JSON_ARRAY('high_risk'),
    JSON_ARRAY('high_risk_system'),
    '2024-08-01',
    NULL,
    TRUE,
    'https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=celex%3A32024R1689',
    'Human oversight must be effective and enabled through appropriate interface design, not just theoretical.'
),

-- Accuracy, Robustness and Cybersecurity - Article 15
(
    'EU_AI_ACT_ART_15',
    'v1.0',
    'Accuracy, robustness and cybersecurity of high-risk AI systems',
    'High-risk AI systems shall be designed and developed in such a way as to achieve an appropriate level of accuracy, robustness and cybersecurity',
    'High-risk AI systems shall be designed and developed in such a way as to achieve, in the light of their intended purpose, an appropriate level of accuracy, robustness and cybersecurity, and to perform consistently in those respects throughout their lifecycle.',
    'eu_ai_act',
    'high',
    'Article 15',
    JSON_ARRAY('high_risk'),
    JSON_ARRAY('high_risk_system'),
    '2024-08-01',
    NULL,
    TRUE,
    'https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=celex%3A32024R1689',
    'Accuracy, robustness, and cybersecurity levels must be appropriate to the intended purpose and maintained throughout the lifecycle.'
),

-- Prohibited AI Practices - Article 5
(
    'EU_AI_ACT_ART_5',
    'v1.0',
    'Prohibited artificial intelligence practices',
    'The following artificial intelligence practices shall be prohibited',
    'The following artificial intelligence practices shall be prohibited: (a) the placing on the market, the putting into service for this specific purpose, or the use of an AI system that deploys subliminal techniques beyond a person\'s consciousness or purposefully manipulative or deceptive techniques, with the objective, or the effect of materially distorting a person\'s behaviour in a manner that causes or is reasonably likely to cause that person or another person significant harm.',
    'eu_ai_act',
    'critical',
    'Article 5',
    JSON_ARRAY('prohibited'),
    JSON_ARRAY('manipulative_system', 'subliminal_system'),
    '2024-08-01',
    NULL,
    TRUE,
    'https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=celex%3A32024R1689',
    'Absolute prohibition - these AI practices cannot be deployed under any circumstances within the EU.'
),

-- General Purpose AI Models - Article 51
(
    'EU_AI_ACT_ART_51',
    'v1.0',
    'Obligations for providers of general-purpose AI models',
    'Providers of general-purpose AI models shall ensure compliance with specific obligations',
    'Providers of general-purpose AI models shall ensure that such models comply with the obligations set out in this Article and in Article 53, as applicable.',
    'eu_ai_act',
    'medium',
    'Article 51',
    JSON_ARRAY('general_purpose'),
    JSON_ARRAY('foundation_model', 'large_language_model'),
    '2024-08-01',
    NULL,
    TRUE,
    'https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=celex%3A32024R1689',
    'Applies to providers of foundational AI models that can be adapted for various downstream applications.'
);