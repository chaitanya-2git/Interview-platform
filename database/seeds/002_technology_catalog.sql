INSERT INTO technology_categories(name,slug,sort_order) VALUES
('Programming Languages','programming-languages',10),
('Frontend & Web','frontend-web',20),
('Backend & APIs','backend-apis',30),
('Databases & Search','databases-search',40),
('Cloud - AWS','cloud-aws',50),
('Cloud - Microsoft Azure','cloud-azure',60),
('Cloud - Google Cloud','cloud-google',70),
('DevOps, Platform & SRE','devops-platform-sre',80),
('Data Engineering & Big Data','data-engineering-big-data',90),
('AI, ML & Data Science','ai-ml-data-science',100),
('Testing & QA','testing-qa',110),
('Mobile Development','mobile-development',120),
('Cybersecurity','cybersecurity',130),
('Salesforce','salesforce',140),
('ServiceNow','servicenow',150),
('SAP','sap',160),
('Microsoft Business & Low Code','microsoft-business-low-code',170),
('BI & Analytics','bi-analytics',180),
('Messaging & Integration','messaging-integration',190),
('Architecture & Engineering','architecture-engineering',200),
('RPA & Automation','rpa-automation',210),
('Collaboration & Delivery','collaboration-delivery',220) ON DUPLICATE KEY UPDATE name=VALUES(name),sort_order=VALUES(sort_order);

SET @category_id=(SELECT id FROM technology_categories WHERE slug='programming-languages');
INSERT INTO technologies(name,active,category_id) VALUES
('Java',1,@category_id),
('Python',1,@category_id),
('JavaScript',1,@category_id),
('TypeScript',1,@category_id),
('C',1,@category_id),
('C++',1,@category_id),
('C#',1,@category_id),
('Go',1,@category_id),
('Rust',1,@category_id),
('PHP',1,@category_id),
('Ruby',1,@category_id),
('Kotlin',1,@category_id),
('Swift',1,@category_id),
('Dart',1,@category_id),
('Scala',1,@category_id),
('R',1,@category_id),
('MATLAB',1,@category_id),
('Perl',1,@category_id),
('Groovy',1,@category_id),
('Shell Scripting',1,@category_id),
('Bash',1,@category_id),
('PowerShell',1,@category_id),
('SQL',1,@category_id),
('PL/SQL',1,@category_id),
('T-SQL',1,@category_id),
('ABAP',1,@category_id),
('Apex',1,@category_id) ON DUPLICATE KEY UPDATE category_id=VALUES(category_id);

SET @category_id=(SELECT id FROM technology_categories WHERE slug='frontend-web');
INSERT INTO technologies(name,active,category_id) VALUES
('HTML',1,@category_id),
('CSS',1,@category_id),
('Sass',1,@category_id),
('Tailwind CSS',1,@category_id),
('Bootstrap',1,@category_id),
('React.js',1,@category_id),
('Next.js',1,@category_id),
('Angular',1,@category_id),
('Vue.js',1,@category_id),
('Nuxt.js',1,@category_id),
('Svelte',1,@category_id),
('SvelteKit',1,@category_id),
('jQuery',1,@category_id),
('Redux',1,@category_id),
('Redux Toolkit',1,@category_id),
('Zustand',1,@category_id),
('Material UI',1,@category_id),
('Ant Design',1,@category_id),
('Webpack',1,@category_id),
('Vite',1,@category_id),
('Storybook',1,@category_id),
('Web Components',1,@category_id) ON DUPLICATE KEY UPDATE category_id=VALUES(category_id);

SET @category_id=(SELECT id FROM technology_categories WHERE slug='backend-apis');
INSERT INTO technologies(name,active,category_id) VALUES
('Node.js',1,@category_id),
('Express.js',1,@category_id),
('NestJS',1,@category_id),
('Java Spring',1,@category_id),
('Spring Boot',1,@category_id),
('Spring MVC',1,@category_id),
('Spring Security',1,@category_id),
('Hibernate',1,@category_id),
('JPA',1,@category_id),
('.NET',1,@category_id),
('ASP.NET Core',1,@category_id),
('Entity Framework',1,@category_id),
('Django',1,@category_id),
('Flask',1,@category_id),
('FastAPI',1,@category_id),
('Ruby on Rails',1,@category_id),
('Laravel',1,@category_id),
('Symfony',1,@category_id),
('CodeIgniter',1,@category_id),
('Gin',1,@category_id),
('Fiber',1,@category_id),
('GraphQL',1,@category_id),
('REST APIs',1,@category_id),
('gRPC',1,@category_id),
('WebSockets',1,@category_id),
('Microservices',1,@category_id) ON DUPLICATE KEY UPDATE category_id=VALUES(category_id);

SET @category_id=(SELECT id FROM technology_categories WHERE slug='databases-search');
INSERT INTO technologies(name,active,category_id) VALUES
('MySQL',1,@category_id),
('PostgreSQL',1,@category_id),
('Microsoft SQL Server',1,@category_id),
('Oracle Database',1,@category_id),
('MariaDB',1,@category_id),
('SQLite',1,@category_id),
('MongoDB',1,@category_id),
('Redis',1,@category_id),
('Amazon DynamoDB',1,@category_id),
('Cassandra',1,@category_id),
('Couchbase',1,@category_id),
('Neo4j',1,@category_id),
('Elasticsearch',1,@category_id),
('OpenSearch',1,@category_id),
('Snowflake',1,@category_id),
('BigQuery',1,@category_id),
('Amazon Redshift',1,@category_id),
('Firebase Firestore',1,@category_id),
('Supabase',1,@category_id) ON DUPLICATE KEY UPDATE category_id=VALUES(category_id);

SET @category_id=(SELECT id FROM technology_categories WHERE slug='cloud-aws');
INSERT INTO technologies(name,active,category_id) VALUES
('AWS',1,@category_id),
('Amazon EC2',1,@category_id),
('Amazon S3',1,@category_id),
('AWS Lambda',1,@category_id),
('Amazon RDS',1,@category_id),
('Amazon ECS',1,@category_id),
('Amazon EKS',1,@category_id),
('AWS Fargate',1,@category_id),
('Amazon CloudFront',1,@category_id),
('Amazon Route 53',1,@category_id),
('Amazon SQS',1,@category_id),
('Amazon SNS',1,@category_id),
('Amazon API Gateway',1,@category_id),
('AWS IAM',1,@category_id),
('AWS CloudFormation',1,@category_id),
('AWS CDK',1,@category_id),
('Amazon CloudWatch',1,@category_id) ON DUPLICATE KEY UPDATE category_id=VALUES(category_id);

SET @category_id=(SELECT id FROM technology_categories WHERE slug='cloud-azure');
INSERT INTO technologies(name,active,category_id) VALUES
('Microsoft Azure',1,@category_id),
('Azure Virtual Machines',1,@category_id),
('Azure App Service',1,@category_id),
('Azure Functions',1,@category_id),
('Azure SQL',1,@category_id),
('Azure Storage',1,@category_id),
('Azure Kubernetes Service',1,@category_id),
('Azure DevOps',1,@category_id),
('Microsoft Entra ID',1,@category_id),
('Azure Key Vault',1,@category_id),
('Azure Monitor',1,@category_id),
('Azure Data Factory',1,@category_id),
('Azure Synapse Analytics',1,@category_id) ON DUPLICATE KEY UPDATE category_id=VALUES(category_id);

SET @category_id=(SELECT id FROM technology_categories WHERE slug='cloud-google');
INSERT INTO technologies(name,active,category_id) VALUES
('Google Cloud Platform',1,@category_id),
('Google Compute Engine',1,@category_id),
('Google Cloud Storage',1,@category_id),
('Google Kubernetes Engine',1,@category_id),
('Cloud Run',1,@category_id),
('Cloud Functions',1,@category_id),
('Cloud SQL',1,@category_id),
('Pub/Sub',1,@category_id),
('Firebase',1,@category_id) ON DUPLICATE KEY UPDATE category_id=VALUES(category_id);

SET @category_id=(SELECT id FROM technology_categories WHERE slug='devops-platform-sre');
INSERT INTO technologies(name,active,category_id) VALUES
('Git',1,@category_id),
('GitHub',1,@category_id),
('GitLab',1,@category_id),
('Bitbucket',1,@category_id),
('Docker',1,@category_id),
('Kubernetes',1,@category_id),
('Helm',1,@category_id),
('Terraform',1,@category_id),
('Ansible',1,@category_id),
('Jenkins',1,@category_id),
('GitHub Actions',1,@category_id),
('GitLab CI/CD',1,@category_id),
('Argo CD',1,@category_id),
('CircleCI',1,@category_id),
('SonarQube',1,@category_id),
('Nexus Repository',1,@category_id),
('JFrog Artifactory',1,@category_id),
('Prometheus',1,@category_id),
('Grafana',1,@category_id),
('Datadog',1,@category_id),
('New Relic',1,@category_id),
('Splunk',1,@category_id),
('ELK Stack',1,@category_id),
('Nginx',1,@category_id),
('Apache HTTP Server',1,@category_id),
('Linux',1,@category_id),
('Ubuntu',1,@category_id),
('Red Hat Enterprise Linux',1,@category_id),
('CI/CD',1,@category_id),
('Infrastructure as Code',1,@category_id),
('Site Reliability Engineering',1,@category_id) ON DUPLICATE KEY UPDATE category_id=VALUES(category_id);

SET @category_id=(SELECT id FROM technology_categories WHERE slug='data-engineering-big-data');
INSERT INTO technologies(name,active,category_id) VALUES
('Apache Spark',1,@category_id),
('PySpark',1,@category_id),
('Apache Hadoop',1,@category_id),
('Apache Hive',1,@category_id),
('Apache Kafka',1,@category_id),
('Apache Flink',1,@category_id),
('Apache Airflow',1,@category_id),
('Databricks',1,@category_id),
('dbt',1,@category_id),
('ETL',1,@category_id),
('ELT',1,@category_id),
('Data Warehousing',1,@category_id),
('Data Lakes',1,@category_id),
('Delta Lake',1,@category_id),
('AWS Glue',1,@category_id),
('Amazon EMR',1,@category_id),
('Azure Databricks',1,@category_id),
('Google Dataflow',1,@category_id),
('Informatica',1,@category_id),
('Talend',1,@category_id) ON DUPLICATE KEY UPDATE category_id=VALUES(category_id);

SET @category_id=(SELECT id FROM technology_categories WHERE slug='ai-ml-data-science');
INSERT INTO technologies(name,active,category_id) VALUES
('Machine Learning',1,@category_id),
('Deep Learning',1,@category_id),
('Artificial Intelligence',1,@category_id),
('Generative AI',1,@category_id),
('LLMs',1,@category_id),
('Natural Language Processing',1,@category_id),
('Computer Vision',1,@category_id),
('Prompt Engineering',1,@category_id),
('RAG',1,@category_id),
('AI Agents',1,@category_id),
('LangChain',1,@category_id),
('LlamaIndex',1,@category_id),
('OpenAI API',1,@category_id),
('Hugging Face',1,@category_id),
('TensorFlow',1,@category_id),
('PyTorch',1,@category_id),
('Keras',1,@category_id),
('scikit-learn',1,@category_id),
('XGBoost',1,@category_id),
('Pandas',1,@category_id),
('NumPy',1,@category_id),
('SciPy',1,@category_id),
('Matplotlib',1,@category_id),
('Jupyter',1,@category_id),
('MLflow',1,@category_id),
('MLOps',1,@category_id),
('ONNX',1,@category_id) ON DUPLICATE KEY UPDATE category_id=VALUES(category_id);

SET @category_id=(SELECT id FROM technology_categories WHERE slug='testing-qa');
INSERT INTO technologies(name,active,category_id) VALUES
('Manual Testing',1,@category_id),
('Automation Testing',1,@category_id),
('Selenium',1,@category_id),
('Cypress',1,@category_id),
('Playwright',1,@category_id),
('Appium',1,@category_id),
('JUnit',1,@category_id),
('TestNG',1,@category_id),
('PyTest',1,@category_id),
('Jest',1,@category_id),
('Vitest',1,@category_id),
('Mocha',1,@category_id),
('Postman',1,@category_id),
('REST Assured',1,@category_id),
('Cucumber',1,@category_id),
('BDD',1,@category_id),
('TDD',1,@category_id),
('API Testing',1,@category_id),
('Performance Testing',1,@category_id),
('JMeter',1,@category_id),
('LoadRunner',1,@category_id),
('Security Testing',1,@category_id) ON DUPLICATE KEY UPDATE category_id=VALUES(category_id);

SET @category_id=(SELECT id FROM technology_categories WHERE slug='mobile-development');
INSERT INTO technologies(name,active,category_id) VALUES
('Android',1,@category_id),
('Android SDK',1,@category_id),
('Kotlin Android',1,@category_id),
('Java Android',1,@category_id),
('iOS',1,@category_id),
('SwiftUI',1,@category_id),
('UIKit',1,@category_id),
('React Native',1,@category_id),
('Flutter',1,@category_id),
('Ionic',1,@category_id),
('Expo',1,@category_id) ON DUPLICATE KEY UPDATE category_id=VALUES(category_id);

SET @category_id=(SELECT id FROM technology_categories WHERE slug='cybersecurity');
INSERT INTO technologies(name,active,category_id) VALUES
('Cybersecurity',1,@category_id),
('Application Security',1,@category_id),
('Network Security',1,@category_id),
('Cloud Security',1,@category_id),
('DevSecOps',1,@category_id),
('IAM',1,@category_id),
('OAuth 2.0',1,@category_id),
('OpenID Connect',1,@category_id),
('JWT',1,@category_id),
('SAML',1,@category_id),
('Penetration Testing',1,@category_id),
('Vulnerability Assessment',1,@category_id),
('OWASP',1,@category_id),
('OWASP Top 10',1,@category_id),
('Burp Suite',1,@category_id),
('Wireshark',1,@category_id),
('Nmap',1,@category_id),
('SIEM',1,@category_id),
('SOC',1,@category_id),
('Microsoft Sentinel',1,@category_id),
('Splunk Security',1,@category_id),
('Zero Trust',1,@category_id) ON DUPLICATE KEY UPDATE category_id=VALUES(category_id);

SET @category_id=(SELECT id FROM technology_categories WHERE slug='salesforce');
INSERT INTO technologies(name,active,category_id) VALUES
('Salesforce',1,@category_id),
('Salesforce Admin',1,@category_id),
('Salesforce Development',1,@category_id),
('Lightning Web Components',1,@category_id),
('Visualforce',1,@category_id),
('SOQL',1,@category_id),
('SOSL',1,@category_id),
('Salesforce Flow',1,@category_id),
('Salesforce Integration',1,@category_id),
('Sales Cloud',1,@category_id),
('Service Cloud',1,@category_id),
('Experience Cloud',1,@category_id),
('Marketing Cloud',1,@category_id),
('CPQ',1,@category_id) ON DUPLICATE KEY UPDATE category_id=VALUES(category_id);

SET @category_id=(SELECT id FROM technology_categories WHERE slug='servicenow');
INSERT INTO technologies(name,active,category_id) VALUES
('ServiceNow',1,@category_id),
('ServiceNow Administration',1,@category_id),
('ServiceNow Development',1,@category_id),
('ITSM',1,@category_id),
('ITOM',1,@category_id),
('ITAM',1,@category_id),
('CMDB',1,@category_id),
('ServiceNow HRSD',1,@category_id),
('ServiceNow CSM',1,@category_id),
('ServiceNow Discovery',1,@category_id),
('ServiceNow IntegrationHub',1,@category_id),
('ServiceNow Flow Designer',1,@category_id) ON DUPLICATE KEY UPDATE category_id=VALUES(category_id);

SET @category_id=(SELECT id FROM technology_categories WHERE slug='sap');
INSERT INTO technologies(name,active,category_id) VALUES
('SAP',1,@category_id),
('SAP ABAP',1,@category_id),
('SAP S/4HANA',1,@category_id),
('SAP HANA',1,@category_id),
('SAP FICO',1,@category_id),
('SAP MM',1,@category_id),
('SAP SD',1,@category_id),
('SAP PP',1,@category_id),
('SAP QM',1,@category_id),
('SAP PM',1,@category_id),
('SAP BW',1,@category_id),
('SAP BI',1,@category_id),
('SAP Basis',1,@category_id),
('SAP Security',1,@category_id),
('SAP SuccessFactors',1,@category_id),
('SAP Ariba',1,@category_id),
('SAP BTP',1,@category_id),
('SAP CPI',1,@category_id),
('SAP Fiori',1,@category_id),
('SAP UI5',1,@category_id) ON DUPLICATE KEY UPDATE category_id=VALUES(category_id);

SET @category_id=(SELECT id FROM technology_categories WHERE slug='microsoft-business-low-code');
INSERT INTO technologies(name,active,category_id) VALUES
('Microsoft Dynamics 365',1,@category_id),
('Dynamics 365 CRM',1,@category_id),
('Power Apps',1,@category_id),
('Power Automate',1,@category_id),
('Power Pages',1,@category_id),
('SharePoint',1,@category_id),
('Microsoft 365',1,@category_id) ON DUPLICATE KEY UPDATE category_id=VALUES(category_id);

SET @category_id=(SELECT id FROM technology_categories WHERE slug='bi-analytics');
INSERT INTO technologies(name,active,category_id) VALUES
('Power BI',1,@category_id),
('Tableau',1,@category_id),
('Looker',1,@category_id),
('Looker Studio',1,@category_id),
('Qlik Sense',1,@category_id),
('Excel',1,@category_id),
('Advanced Excel',1,@category_id),
('SSRS',1,@category_id),
('SSIS',1,@category_id),
('SSAS',1,@category_id),
('Data Visualization',1,@category_id),
('Business Intelligence',1,@category_id) ON DUPLICATE KEY UPDATE category_id=VALUES(category_id);

SET @category_id=(SELECT id FROM technology_categories WHERE slug='messaging-integration');
INSERT INTO technologies(name,active,category_id) VALUES
('RabbitMQ',1,@category_id),
('ActiveMQ',1,@category_id),
('IBM MQ',1,@category_id),
('Azure Service Bus',1,@category_id),
('MuleSoft',1,@category_id),
('Boomi',1,@category_id),
('Apache Camel',1,@category_id),
('SOAP',1,@category_id) ON DUPLICATE KEY UPDATE category_id=VALUES(category_id);

SET @category_id=(SELECT id FROM technology_categories WHERE slug='architecture-engineering');
INSERT INTO technologies(name,active,category_id) VALUES
('System Design',1,@category_id),
('Software Architecture',1,@category_id),
('Microservices Architecture',1,@category_id),
('Event-Driven Architecture',1,@category_id),
('Distributed Systems',1,@category_id),
('Design Patterns',1,@category_id),
('Domain-Driven Design',1,@category_id),
('Clean Architecture',1,@category_id),
('SOLID Principles',1,@category_id),
('API Design',1,@category_id) ON DUPLICATE KEY UPDATE category_id=VALUES(category_id);

SET @category_id=(SELECT id FROM technology_categories WHERE slug='rpa-automation');
INSERT INTO technologies(name,active,category_id) VALUES
('UiPath',1,@category_id),
('Automation Anywhere',1,@category_id),
('Blue Prism',1,@category_id),
('Robotic Process Automation',1,@category_id) ON DUPLICATE KEY UPDATE category_id=VALUES(category_id);

SET @category_id=(SELECT id FROM technology_categories WHERE slug='collaboration-delivery');
INSERT INTO technologies(name,active,category_id) VALUES
('Jira',1,@category_id),
('Confluence',1,@category_id),
('Agile',1,@category_id),
('Scrum',1,@category_id),
('Kanban',1,@category_id),
('Unix',1,@category_id),
('JSON',1,@category_id),
('XML',1,@category_id),
('YAML',1,@category_id) ON DUPLICATE KEY UPDATE category_id=VALUES(category_id);

UPDATE technologies SET active=0 WHERE LOWER(TRIM(name))='general';

INSERT INTO technology_aliases(technology_id,alias,normalized_alias) SELECT id,'React','react' FROM technologies WHERE name='React.js' ON DUPLICATE KEY UPDATE technology_id=VALUES(technology_id),alias=VALUES(alias);
INSERT INTO technology_aliases(technology_id,alias,normalized_alias) SELECT id,'ReactJS','reactjs' FROM technologies WHERE name='React.js' ON DUPLICATE KEY UPDATE technology_id=VALUES(technology_id),alias=VALUES(alias);
INSERT INTO technology_aliases(technology_id,alias,normalized_alias) SELECT id,'Node','node' FROM technologies WHERE name='Node.js' ON DUPLICATE KEY UPDATE technology_id=VALUES(technology_id),alias=VALUES(alias);
INSERT INTO technology_aliases(technology_id,alias,normalized_alias) SELECT id,'NodeJS','nodejs' FROM technologies WHERE name='Node.js' ON DUPLICATE KEY UPDATE technology_id=VALUES(technology_id),alias=VALUES(alias);
INSERT INTO technology_aliases(technology_id,alias,normalized_alias) SELECT id,'Springboot','springboot' FROM technologies WHERE name='Spring Boot' ON DUPLICATE KEY UPDATE technology_id=VALUES(technology_id),alias=VALUES(alias);
INSERT INTO technology_aliases(technology_id,alias,normalized_alias) SELECT id,'Postgres','postgres' FROM technologies WHERE name='PostgreSQL' ON DUPLICATE KEY UPDATE technology_id=VALUES(technology_id),alias=VALUES(alias);
INSERT INTO technology_aliases(technology_id,alias,normalized_alias) SELECT id,'K8s','k8s' FROM technologies WHERE name='Kubernetes' ON DUPLICATE KEY UPDATE technology_id=VALUES(technology_id),alias=VALUES(alias);
INSERT INTO technology_aliases(technology_id,alias,normalized_alias) SELECT id,'GCP','gcp' FROM technologies WHERE name='Google Cloud Platform' ON DUPLICATE KEY UPDATE technology_id=VALUES(technology_id),alias=VALUES(alias);
INSERT INTO technology_aliases(technology_id,alias,normalized_alias) SELECT id,'Amazon Web Services','amazonwebservices' FROM technologies WHERE name='AWS' ON DUPLICATE KEY UPDATE technology_id=VALUES(technology_id),alias=VALUES(alias);
INSERT INTO technology_aliases(technology_id,alias,normalized_alias) SELECT id,'Azure','azure' FROM technologies WHERE name='Microsoft Azure' ON DUPLICATE KEY UPDATE technology_id=VALUES(technology_id),alias=VALUES(alias);
INSERT INTO technology_aliases(technology_id,alias,normalized_alias) SELECT id,'Dotnet','dotnet' FROM technologies WHERE name='.NET' ON DUPLICATE KEY UPDATE technology_id=VALUES(technology_id),alias=VALUES(alias);
INSERT INTO technology_aliases(technology_id,alias,normalized_alias) SELECT id,'C Sharp','csharp' FROM technologies WHERE name='C#' ON DUPLICATE KEY UPDATE technology_id=VALUES(technology_id),alias=VALUES(alias);
INSERT INTO technology_aliases(technology_id,alias,normalized_alias) SELECT id,'CSharp','csharp' FROM technologies WHERE name='C#' ON DUPLICATE KEY UPDATE technology_id=VALUES(technology_id),alias=VALUES(alias);
INSERT INTO technology_aliases(technology_id,alias,normalized_alias) SELECT id,'JS','js' FROM technologies WHERE name='JavaScript' ON DUPLICATE KEY UPDATE technology_id=VALUES(technology_id),alias=VALUES(alias);
INSERT INTO technology_aliases(technology_id,alias,normalized_alias) SELECT id,'TS','ts' FROM technologies WHERE name='TypeScript' ON DUPLICATE KEY UPDATE technology_id=VALUES(technology_id),alias=VALUES(alias);
INSERT INTO technology_aliases(technology_id,alias,normalized_alias) SELECT id,'GenAI','genai' FROM technologies WHERE name='Generative AI' ON DUPLICATE KEY UPDATE technology_id=VALUES(technology_id),alias=VALUES(alias);
INSERT INTO technology_aliases(technology_id,alias,normalized_alias) SELECT id,'LLM','llm' FROM technologies WHERE name='LLMs' ON DUPLICATE KEY UPDATE technology_id=VALUES(technology_id),alias=VALUES(alias);
INSERT INTO technology_aliases(technology_id,alias,normalized_alias) SELECT id,'MSSQL','mssql' FROM technologies WHERE name='Microsoft SQL Server' ON DUPLICATE KEY UPDATE technology_id=VALUES(technology_id),alias=VALUES(alias);
INSERT INTO technology_aliases(technology_id,alias,normalized_alias) SELECT id,'PowerBI','powerbi' FROM technologies WHERE name='Power BI' ON DUPLICATE KEY UPDATE technology_id=VALUES(technology_id),alias=VALUES(alias);
INSERT INTO technology_aliases(technology_id,alias,normalized_alias) SELECT id,'ReactNative','reactnative' FROM technologies WHERE name='React Native' ON DUPLICATE KEY UPDATE technology_id=VALUES(technology_id),alias=VALUES(alias);
INSERT INTO technology_aliases(technology_id,alias,normalized_alias) SELECT id,'SNOW','snow' FROM technologies WHERE name='ServiceNow' ON DUPLICATE KEY UPDATE technology_id=VALUES(technology_id),alias=VALUES(alias);
INSERT INTO technology_aliases(technology_id,alias,normalized_alias) SELECT id,'DynamoDB','dynamodb' FROM technologies WHERE name='Amazon DynamoDB' ON DUPLICATE KEY UPDATE technology_id=VALUES(technology_id),alias=VALUES(alias);
