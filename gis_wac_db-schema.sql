--
-- PostgreSQL database dump
--

\restrict JAJp7xjo7UfYSqVf1KMlSmyouCeb2qQmnsCm3395FJUEgwpXWVadiROOLujF5NV

-- Dumped from database version 18.1 (Ubuntu 18.1-1.pgdg25.10+2)
-- Dumped by pg_dump version 18.1 (Ubuntu 18.1-1.pgdg25.10+2)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: uuid-ossp; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS "uuid-ossp" WITH SCHEMA public;


--
-- Name: EXTENSION "uuid-ossp"; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION "uuid-ossp" IS 'generate universally unique identifiers (UUIDs)';


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: alert_deliveries; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.alert_deliveries (
    delivery_id bigint NOT NULL,
    queue_id bigint,
    delivery_method character varying(10) NOT NULL,
    recipient_address character varying(255) NOT NULL,
    message_content text NOT NULL,
    status character varying(20) NOT NULL,
    external_message_id character varying(255),
    sent_at timestamp with time zone DEFAULT now(),
    delivered_at timestamp with time zone,
    failed_at timestamp with time zone,
    failure_reason text,
    retry_count integer DEFAULT 0,
    next_retry_at timestamp with time zone,
    CONSTRAINT valid_delivery_method CHECK (((delivery_method)::text = ANY ((ARRAY['EMAIL'::character varying, 'SMS'::character varying, 'PUSH'::character varying])::text[]))),
    CONSTRAINT valid_delivery_status CHECK (((status)::text = ANY ((ARRAY['SENT'::character varying, 'DELIVERED'::character varying, 'FAILED'::character varying, 'BOUNCED'::character varying])::text[])))
);


ALTER TABLE public.alert_deliveries OWNER TO postgres;

--
-- Name: alert_deliveries_delivery_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.alert_deliveries_delivery_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.alert_deliveries_delivery_id_seq OWNER TO postgres;

--
-- Name: alert_deliveries_delivery_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.alert_deliveries_delivery_id_seq OWNED BY public.alert_deliveries.delivery_id;


--
-- Name: alert_queue; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.alert_queue (
    queue_id bigint NOT NULL,
    rule_id uuid,
    location_id uuid,
    history_id bigint,
    status character varying(20) DEFAULT 'QUEUED'::character varying NOT NULL,
    previous_status character varying(20),
    triggered_at timestamp with time zone DEFAULT now() NOT NULL,
    acknowledged_at timestamp with time zone,
    escalated_at timestamp with time zone,
    sent_at timestamp with time zone,
    delivered_at timestamp with time zone,
    expired_at timestamp with time zone,
    cancelled_at timestamp with time zone,
    calculated_value jsonb NOT NULL,
    alert_message text NOT NULL,
    alert_subject character varying(255),
    custom_data jsonb,
    delivery_attempts integer DEFAULT 0,
    last_delivery_attempt timestamp with time zone,
    delivery_failure_reason text,
    escalation_level integer DEFAULT 1,
    parent_alert_id bigint,
    correlation_id uuid DEFAULT public.uuid_generate_v4(),
    version integer DEFAULT 1,
    resolved_at timestamp with time zone,
    CONSTRAINT alert_queue_status_check CHECK (((status)::text = ANY ((ARRAY['ACTIVE'::character varying, 'SENT'::character varying, 'FAILED'::character varying, 'RESOLVED'::character varying, 'CLEARED'::character varying, 'CANCELLED'::character varying, 'EXPIRED'::character varying])::text[]))),
    CONSTRAINT valid_status CHECK (((status)::text = ANY ((ARRAY['ACTIVE'::character varying, 'SENT'::character varying, 'FAILED'::character varying, 'RESOLVED'::character varying, 'CLEARED'::character varying, 'CANCELLED'::character varying, 'EXPIRED'::character varying])::text[])))
);


ALTER TABLE public.alert_queue OWNER TO postgres;

--
-- Name: alert_queue_queue_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.alert_queue_queue_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.alert_queue_queue_id_seq OWNER TO postgres;

--
-- Name: alert_queue_queue_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.alert_queue_queue_id_seq OWNED BY public.alert_queue.queue_id;


--
-- Name: alert_rules; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.alert_rules (
    rule_id uuid DEFAULT public.uuid_generate_v4() NOT NULL,
    location_id uuid,
    rule_name character varying(255) NOT NULL,
    rule_description text,
    rule_category character varying(50) DEFAULT 'WEATHER'::character varying,
    target_metric character varying(100) NOT NULL,
    operator character varying(10) NOT NULL,
    threshold_value character varying(500) NOT NULL,
    threshold_value_2 character varying(500),
    severity_level character varying(20) DEFAULT 'MEDIUM'::character varying NOT NULL,
    condition_type character varying(20) DEFAULT 'INSTANT'::character varying,
    duration_minutes integer,
    trend_period_minutes integer,
    parent_rule_id uuid,
    logical_operator character varying(3),
    execution_order integer DEFAULT 1,
    message_template text NOT NULL,
    custom_subject character varying(255),
    is_template_rich_text boolean DEFAULT false,
    cooldown_period_minutes integer DEFAULT 60 NOT NULL,
    max_alerts_per_hour integer DEFAULT 10,
    throttle_enabled boolean DEFAULT true,
    escalation_enabled boolean DEFAULT false,
    escalation_after_minutes integer DEFAULT 30,
    escalation_rule_id uuid,
    is_active boolean DEFAULT true,
    is_system_rule boolean DEFAULT false,
    activation_date timestamp with time zone DEFAULT now(),
    deactivation_date timestamp with time zone,
    created_at timestamp with time zone DEFAULT now(),
    updated_at timestamp with time zone DEFAULT now(),
    CONSTRAINT valid_category CHECK (((rule_category)::text = ANY ((ARRAY['WEATHER'::character varying, 'SAFETY'::character varying, 'OPERATIONAL'::character varying, 'MAINTENANCE'::character varying, 'CUSTOM'::character varying])::text[]))),
    CONSTRAINT valid_condition_type CHECK (((condition_type)::text = ANY ((ARRAY['INSTANT'::character varying, 'DURATION'::character varying, 'TREND'::character varying, 'COMPOUND'::character varying])::text[]))),
    CONSTRAINT valid_duration CHECK (((duration_minutes IS NULL) OR (duration_minutes > 0))),
    CONSTRAINT valid_logical_operator CHECK ((((logical_operator)::text = ANY ((ARRAY['AND'::character varying, 'OR'::character varying])::text[])) OR (logical_operator IS NULL))),
    CONSTRAINT valid_operator CHECK (((operator)::text = ANY ((ARRAY['<'::character varying, '>'::character varying, '='::character varying, '!='::character varying, '<='::character varying, '>='::character varying, 'IN'::character varying, 'NOT_IN'::character varying, 'BETWEEN'::character varying])::text[]))),
    CONSTRAINT valid_severity CHECK (((severity_level)::text = ANY ((ARRAY['LOW'::character varying, 'MEDIUM'::character varying, 'HIGH'::character varying, 'CRITICAL'::character varying, 'EMERGENCY'::character varying])::text[]))),
    CONSTRAINT valid_trend_period CHECK (((trend_period_minutes IS NULL) OR (trend_period_minutes > 0)))
);


ALTER TABLE public.alert_rules OWNER TO postgres;

--
-- Name: locations; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.locations (
    location_id uuid DEFAULT public.uuid_generate_v4() NOT NULL,
    location_name character varying(255) NOT NULL,
    location_code character varying(50),
    location_type character varying(50) DEFAULT 'FACILITY'::character varying NOT NULL,
    description text,
    address_line_1 character varying(255) NOT NULL,
    address_line_2 character varying(255),
    building_number character varying(20),
    building_name character varying(100),
    floor_number character varying(10),
    room_suite character varying(50),
    complex_name character varying(100),
    landmark character varying(100),
    neighborhood character varying(100),
    city character varying(100) NOT NULL,
    county character varying(100),
    state_province character varying(100) NOT NULL,
    zip_postal_code character varying(20) NOT NULL,
    country character varying(100) DEFAULT 'United States'::character varying,
    latitude numeric(10,8) NOT NULL,
    longitude numeric(11,8) NOT NULL,
    elevation_meters numeric(8,2),
    geohash character varying(12),
    polling_frequency interval DEFAULT '00:05:00'::interval NOT NULL,
    timezone character varying(100) DEFAULT 'America/New_York'::character varying NOT NULL,
    operational_radius_km numeric(6,2) DEFAULT 1.0,
    status character varying(50) DEFAULT 'ACTIVE'::character varying NOT NULL,
    priority_level character varying(20) DEFAULT 'MEDIUM'::character varying,
    area_sq_meters numeric(10,2),
    surrounding_terrain character varying(50) DEFAULT 'URBAN'::character varying,
    has_weather_station boolean DEFAULT false,
    station_model character varying(100),
    created_at timestamp with time zone DEFAULT now(),
    updated_at timestamp with time zone DEFAULT now(),
    activated_at timestamp with time zone,
    deactivated_at timestamp with time zone,
    CONSTRAINT valid_latitude CHECK (((latitude >= ('-90'::integer)::numeric) AND (latitude <= (90)::numeric))),
    CONSTRAINT valid_location_type CHECK (((location_type)::text = ANY ((ARRAY['FACILITY'::character varying, 'BUILDING'::character varying, 'CAMPUS'::character varying, 'CONSTRUCTION_SITE'::character varying, 'FARM'::character varying, 'WAREHOUSE'::character varying, 'OFFICE'::character varying, 'RETAIL'::character varying, 'RESIDENTIAL'::character varying, 'OTHER'::character varying])::text[]))),
    CONSTRAINT valid_longitude CHECK (((longitude >= ('-180'::integer)::numeric) AND (longitude <= (180)::numeric))),
    CONSTRAINT valid_polling_frequency CHECK ((polling_frequency > '00:00:00'::interval)),
    CONSTRAINT valid_priority CHECK (((priority_level)::text = ANY ((ARRAY['LOW'::character varying, 'MEDIUM'::character varying, 'HIGH'::character varying, 'CRITICAL'::character varying])::text[]))),
    CONSTRAINT valid_status CHECK (((status)::text = ANY ((ARRAY['ACTIVE'::character varying, 'INACTIVE'::character varying, 'MAINTENANCE'::character varying, 'CONSTRUCTION'::character varying, 'DECOMMISSIONED'::character varying])::text[]))),
    CONSTRAINT valid_terrain CHECK (((surrounding_terrain)::text = ANY ((ARRAY['URBAN'::character varying, 'SUBURBAN'::character varying, 'RURAL'::character varying, 'COASTAL'::character varying, 'MOUNTAIN'::character varying, 'FOREST'::character varying, 'DESERT'::character varying, 'WETLAND'::character varying])::text[])))
);


ALTER TABLE public.locations OWNER TO postgres;

--
-- Name: system_config; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.system_config (
    config_id integer NOT NULL,
    config_key character varying(100) NOT NULL,
    config_value text,
    config_type character varying(20) DEFAULT 'string'::character varying,
    description text,
    updated_at timestamp with time zone DEFAULT now()
);


ALTER TABLE public.system_config OWNER TO postgres;

--
-- Name: system_config_config_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.system_config_config_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.system_config_config_id_seq OWNER TO postgres;

--
-- Name: system_config_config_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.system_config_config_id_seq OWNED BY public.system_config.config_id;


--
-- Name: user_locations; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.user_locations (
    user_location_id uuid DEFAULT public.uuid_generate_v4() NOT NULL,
    user_id uuid,
    location_id uuid,
    relationship_type character varying(50) NOT NULL,
    location_specific_email boolean DEFAULT true,
    location_specific_sms boolean DEFAULT false,
    location_priority integer DEFAULT 1,
    can_acknowledge_alerts boolean DEFAULT false,
    can_create_rules boolean DEFAULT false,
    can_manage_location boolean DEFAULT false,
    valid_from timestamp with time zone DEFAULT now(),
    valid_until timestamp with time zone,
    is_current boolean DEFAULT true,
    assigned_at timestamp with time zone DEFAULT now(),
    updated_at timestamp with time zone DEFAULT now(),
    CONSTRAINT valid_dates CHECK (((valid_until IS NULL) OR (valid_until > valid_from))),
    CONSTRAINT valid_relationship_type CHECK (((relationship_type)::text = ANY ((ARRAY['OWNER'::character varying, 'MANAGER'::character varying, 'OPERATOR'::character varying, 'RESIDENT'::character varying, 'TENANT'::character varying, 'EMERGENCY_CONTACT'::character varying, 'MAINTENANCE'::character varying, 'SECURITY'::character varying, 'VIEWER'::character varying, 'CUSTOM'::character varying])::text[])))
);


ALTER TABLE public.user_locations OWNER TO postgres;

--
-- Name: users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.users (
    user_id uuid DEFAULT public.uuid_generate_v4() NOT NULL,
    username character varying(50),
    email character varying(255) NOT NULL,
    password_hash character varying(255),
    phone_country_code character varying(5) DEFAULT '+1'::character varying,
    phone_number character varying(20),
    phone_verified boolean DEFAULT false,
    email_verified boolean DEFAULT false,
    first_name character varying(100) NOT NULL,
    middle_name character varying(100),
    last_name character varying(100) NOT NULL,
    suffix character varying(10),
    title character varying(50),
    address_line_1 character varying(255) NOT NULL,
    address_line_2 character varying(255),
    building_number character varying(20),
    building_name character varying(100),
    city character varying(100) NOT NULL,
    state_province character varying(100) NOT NULL,
    zip_postal_code character varying(20) NOT NULL,
    country character varying(100) DEFAULT 'United States'::character varying,
    prefers_email_alerts boolean DEFAULT true,
    prefers_sms_alerts boolean DEFAULT false,
    prefers_push_alerts boolean DEFAULT false,
    alert_timezone character varying(100) DEFAULT 'America/New_York'::character varying NOT NULL,
    daily_alert_digest boolean DEFAULT false,
    digest_time time without time zone DEFAULT '07:00:00'::time without time zone,
    mfa_enabled boolean DEFAULT false,
    mfa_secret character varying(100),
    last_login_at timestamp with time zone,
    failed_login_attempts integer DEFAULT 0,
    account_locked_until timestamp with time zone,
    is_active boolean DEFAULT true,
    is_staff boolean DEFAULT false,
    is_superuser boolean DEFAULT false,
    created_at timestamp with time zone DEFAULT now(),
    updated_at timestamp with time zone DEFAULT now(),
    date_joined timestamp with time zone DEFAULT now(),
    CONSTRAINT valid_email CHECK (((email)::text ~* '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$'::text))
);


ALTER TABLE public.users OWNER TO postgres;

--
-- Name: user_location_details; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW public.user_location_details AS
 SELECT ul.user_location_id,
    u.user_id,
    u.first_name,
    u.last_name,
    u.email,
    u.phone_number,
    l.location_id,
    l.location_name,
    l.location_type,
    l.address_line_1,
    l.city,
    l.state_province,
    l.zip_postal_code,
    ul.relationship_type,
    ul.location_specific_email,
    ul.location_specific_sms,
    ul.can_acknowledge_alerts,
    ul.can_create_rules
   FROM ((public.user_locations ul
     JOIN public.users u ON ((ul.user_id = u.user_id)))
     JOIN public.locations l ON ((ul.location_id = l.location_id)))
  WHERE ((ul.is_current = true) AND (u.is_active = true) AND ((l.status)::text = 'ACTIVE'::text));


ALTER VIEW public.user_location_details OWNER TO postgres;

--
-- Name: user_preferences; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.user_preferences (
    preference_id uuid DEFAULT public.uuid_generate_v4() NOT NULL,
    user_id uuid,
    preference_category character varying(50) NOT NULL,
    preference_key character varying(100) NOT NULL,
    preference_value text,
    data_type character varying(20) DEFAULT 'STRING'::character varying,
    created_at timestamp with time zone DEFAULT now(),
    updated_at timestamp with time zone DEFAULT now(),
    CONSTRAINT valid_data_type CHECK (((data_type)::text = ANY ((ARRAY['STRING'::character varying, 'BOOLEAN'::character varying, 'INTEGER'::character varying, 'JSON'::character varying])::text[])))
);


ALTER TABLE public.user_preferences OWNER TO postgres;

--
-- Name: weather_archive_settings; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.weather_archive_settings (
    setting_id integer NOT NULL,
    location_id uuid NOT NULL,
    archive_frequency character varying(20) DEFAULT 'realtime'::character varying NOT NULL,
    realtime_keep_hours integer DEFAULT 24,
    hourly_keep_days integer DEFAULT 30,
    daily_keep_years integer DEFAULT 2,
    weekly_keep_years integer DEFAULT 5,
    monthly_keep_years integer DEFAULT 10,
    yearly_keep_years integer DEFAULT 20,
    enabled boolean DEFAULT true,
    created_at timestamp with time zone DEFAULT now(),
    updated_at timestamp with time zone DEFAULT now()
);


ALTER TABLE public.weather_archive_settings OWNER TO postgres;

--
-- Name: weather_archive_settings_setting_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.weather_archive_settings_setting_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.weather_archive_settings_setting_id_seq OWNER TO postgres;

--
-- Name: weather_archive_settings_setting_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.weather_archive_settings_setting_id_seq OWNED BY public.weather_archive_settings.setting_id;


--
-- Name: weather_history; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.weather_history (
    history_id bigint NOT NULL,
    location_id uuid,
    recorded_at_utc timestamp with time zone NOT NULL,
    recorded_at_local timestamp with time zone NOT NULL,
    data_effective_time timestamp with time zone,
    ingestion_timestamp timestamp with time zone DEFAULT now(),
    api_source character varying(50) DEFAULT 'OPENWEATHERMAP'::character varying NOT NULL,
    source_id character varying(100),
    data_confidence character varying(20) DEFAULT 'HIGH'::character varying,
    temp_celsius numeric(5,2),
    temp_fahrenheit numeric(5,2) GENERATED ALWAYS AS ((((temp_celsius * (9)::numeric) / (5)::numeric) + (32)::numeric)) STORED,
    feels_like_celsius numeric(5,2),
    humidity_percent integer,
    pressure_hpa numeric(7,2),
    pressure_inhg numeric(6,3) GENERATED ALWAYS AS ((pressure_hpa * 0.02953)) STORED,
    wind_speed_ms numeric(6,2),
    wind_speed_mph numeric(6,2) GENERATED ALWAYS AS ((wind_speed_ms * 2.23694)) STORED,
    wind_direction_degrees integer,
    wind_gust_ms numeric(6,2),
    rainfall_mm_1h numeric(6,2),
    rainfall_mm_3h numeric(6,2),
    snowfall_mm_1h numeric(6,2),
    visibility_meters integer,
    cloudiness_percent integer,
    dew_point_celsius numeric(5,2),
    uv_index numeric(4,1),
    solar_radiation_wm2 numeric(7,2),
    weather_main character varying(50),
    weather_description character varying(100),
    weather_icon character varying(10),
    raw_payload jsonb NOT NULL,
    is_forecast boolean DEFAULT false,
    forecast_hours_ahead integer,
    data_quality_score numeric(3,2) DEFAULT 1.00,
    needs_verification boolean DEFAULT false,
    CONSTRAINT valid_cloudiness CHECK (((cloudiness_percent >= 0) AND (cloudiness_percent <= 100))),
    CONSTRAINT valid_data_confidence CHECK (((data_confidence)::text = ANY ((ARRAY['LOW'::character varying, 'MEDIUM'::character varying, 'HIGH'::character varying, 'VERIFIED'::character varying])::text[]))),
    CONSTRAINT valid_humidity CHECK (((humidity_percent >= 0) AND (humidity_percent <= 100))),
    CONSTRAINT valid_wind_direction CHECK (((wind_direction_degrees >= 0) AND (wind_direction_degrees <= 360)))
);


ALTER TABLE public.weather_history OWNER TO postgres;

--
-- Name: weather_history_history_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.weather_history_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.weather_history_history_id_seq OWNER TO postgres;

--
-- Name: weather_history_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.weather_history_history_id_seq OWNED BY public.weather_history.history_id;


--
-- Name: alert_deliveries delivery_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.alert_deliveries ALTER COLUMN delivery_id SET DEFAULT nextval('public.alert_deliveries_delivery_id_seq'::regclass);


--
-- Name: alert_queue queue_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.alert_queue ALTER COLUMN queue_id SET DEFAULT nextval('public.alert_queue_queue_id_seq'::regclass);


--
-- Name: system_config config_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.system_config ALTER COLUMN config_id SET DEFAULT nextval('public.system_config_config_id_seq'::regclass);


--
-- Name: weather_archive_settings setting_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.weather_archive_settings ALTER COLUMN setting_id SET DEFAULT nextval('public.weather_archive_settings_setting_id_seq'::regclass);


--
-- Name: weather_history history_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.weather_history ALTER COLUMN history_id SET DEFAULT nextval('public.weather_history_history_id_seq'::regclass);


--
-- Name: alert_deliveries alert_deliveries_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.alert_deliveries
    ADD CONSTRAINT alert_deliveries_pkey PRIMARY KEY (delivery_id);


--
-- Name: alert_queue alert_queue_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.alert_queue
    ADD CONSTRAINT alert_queue_pkey PRIMARY KEY (queue_id);


--
-- Name: alert_rules alert_rules_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.alert_rules
    ADD CONSTRAINT alert_rules_pkey PRIMARY KEY (rule_id);


--
-- Name: locations locations_location_code_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.locations
    ADD CONSTRAINT locations_location_code_key UNIQUE (location_code);


--
-- Name: locations locations_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.locations
    ADD CONSTRAINT locations_pkey PRIMARY KEY (location_id);


--
-- Name: system_config system_config_config_key_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.system_config
    ADD CONSTRAINT system_config_config_key_key UNIQUE (config_key);


--
-- Name: system_config system_config_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.system_config
    ADD CONSTRAINT system_config_pkey PRIMARY KEY (config_id);


--
-- Name: weather_history unique_location_record; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.weather_history
    ADD CONSTRAINT unique_location_record UNIQUE (location_id, recorded_at_utc, api_source);


--
-- Name: user_locations unique_user_location; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.user_locations
    ADD CONSTRAINT unique_user_location UNIQUE (user_id, location_id, relationship_type);


--
-- Name: user_preferences unique_user_preference; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.user_preferences
    ADD CONSTRAINT unique_user_preference UNIQUE (user_id, preference_category, preference_key);


--
-- Name: user_locations user_locations_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.user_locations
    ADD CONSTRAINT user_locations_pkey PRIMARY KEY (user_location_id);


--
-- Name: user_preferences user_preferences_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.user_preferences
    ADD CONSTRAINT user_preferences_pkey PRIMARY KEY (preference_id);


--
-- Name: users users_email_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_key UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (user_id);


--
-- Name: users users_username_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_username_key UNIQUE (username);


--
-- Name: weather_archive_settings weather_archive_settings_location_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.weather_archive_settings
    ADD CONSTRAINT weather_archive_settings_location_id_key UNIQUE (location_id);


--
-- Name: weather_archive_settings weather_archive_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.weather_archive_settings
    ADD CONSTRAINT weather_archive_settings_pkey PRIMARY KEY (setting_id);


--
-- Name: weather_history weather_history_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.weather_history
    ADD CONSTRAINT weather_history_pkey PRIMARY KEY (history_id);


--
-- Name: idx_alert_deliveries_method; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_alert_deliveries_method ON public.alert_deliveries USING btree (delivery_method);


--
-- Name: idx_alert_deliveries_queue; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_alert_deliveries_queue ON public.alert_deliveries USING btree (queue_id);


--
-- Name: idx_alert_deliveries_sent_at; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_alert_deliveries_sent_at ON public.alert_deliveries USING btree (sent_at);


--
-- Name: idx_alert_deliveries_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_alert_deliveries_status ON public.alert_deliveries USING btree (status);


--
-- Name: idx_alert_queue_correlation; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_alert_queue_correlation ON public.alert_queue USING btree (correlation_id);


--
-- Name: idx_alert_queue_location; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_alert_queue_location ON public.alert_queue USING btree (location_id);


--
-- Name: idx_alert_queue_parent; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_alert_queue_parent ON public.alert_queue USING btree (parent_alert_id);


--
-- Name: idx_alert_queue_rule; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_alert_queue_rule ON public.alert_queue USING btree (rule_id);


--
-- Name: idx_alert_queue_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_alert_queue_status ON public.alert_queue USING btree (status);


--
-- Name: idx_alert_queue_triggered; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_alert_queue_triggered ON public.alert_queue USING btree (triggered_at);


--
-- Name: idx_alert_rules_active; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_alert_rules_active ON public.alert_rules USING btree (is_active);


--
-- Name: idx_alert_rules_category; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_alert_rules_category ON public.alert_rules USING btree (rule_category);


--
-- Name: idx_alert_rules_created; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_alert_rules_created ON public.alert_rules USING btree (created_at);


--
-- Name: idx_alert_rules_location; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_alert_rules_location ON public.alert_rules USING btree (location_id);


--
-- Name: idx_alert_rules_parent; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_alert_rules_parent ON public.alert_rules USING btree (parent_rule_id);


--
-- Name: idx_alert_rules_severity; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_alert_rules_severity ON public.alert_rules USING btree (severity_level);


--
-- Name: idx_archive_settings_frequency; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_archive_settings_frequency ON public.weather_archive_settings USING btree (archive_frequency);


--
-- Name: idx_archive_settings_location; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_archive_settings_location ON public.weather_archive_settings USING btree (location_id);


--
-- Name: idx_locations_city_state; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_locations_city_state ON public.locations USING btree (city, state_province);


--
-- Name: idx_locations_coords; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_locations_coords ON public.locations USING btree (latitude, longitude);


--
-- Name: idx_locations_created_at; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_locations_created_at ON public.locations USING btree (created_at);


--
-- Name: idx_locations_geohash; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_locations_geohash ON public.locations USING btree (geohash);


--
-- Name: idx_locations_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_locations_status ON public.locations USING btree (status);


--
-- Name: idx_locations_type; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_locations_type ON public.locations USING btree (location_type);


--
-- Name: idx_locations_zip; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_locations_zip ON public.locations USING btree (zip_postal_code);


--
-- Name: idx_user_locations_current; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_user_locations_current ON public.user_locations USING btree (is_current);


--
-- Name: idx_user_locations_location; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_user_locations_location ON public.user_locations USING btree (location_id);


--
-- Name: idx_user_locations_relationship; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_user_locations_relationship ON public.user_locations USING btree (relationship_type);


--
-- Name: idx_user_locations_user; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_user_locations_user ON public.user_locations USING btree (user_id);


--
-- Name: idx_user_preferences_category; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_user_preferences_category ON public.user_preferences USING btree (preference_category);


--
-- Name: idx_user_preferences_key; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_user_preferences_key ON public.user_preferences USING btree (preference_key);


--
-- Name: idx_user_preferences_user; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_user_preferences_user ON public.user_preferences USING btree (user_id);


--
-- Name: idx_users_active; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_users_active ON public.users USING btree (is_active);


--
-- Name: idx_users_created_at; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_users_created_at ON public.users USING btree (created_at);


--
-- Name: idx_users_email; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_users_email ON public.users USING btree (email);


--
-- Name: idx_users_name; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_users_name ON public.users USING btree (first_name, last_name);


--
-- Name: idx_users_phone; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_users_phone ON public.users USING btree (phone_number);


--
-- Name: idx_users_zip; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_users_zip ON public.users USING btree (zip_postal_code);


--
-- Name: idx_weather_history_confidence; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_weather_history_confidence ON public.weather_history USING btree (data_confidence);


--
-- Name: idx_weather_history_location_time; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_weather_history_location_time ON public.weather_history USING btree (location_id, recorded_at_utc);


--
-- Name: idx_weather_history_rain; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_weather_history_rain ON public.weather_history USING btree (rainfall_mm_1h);


--
-- Name: idx_weather_history_raw_gin; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_weather_history_raw_gin ON public.weather_history USING gin (raw_payload);


--
-- Name: idx_weather_history_source; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_weather_history_source ON public.weather_history USING btree (api_source);


--
-- Name: idx_weather_history_temp; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_weather_history_temp ON public.weather_history USING btree (temp_celsius);


--
-- Name: idx_weather_history_timestamp; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_weather_history_timestamp ON public.weather_history USING btree (recorded_at_utc);


--
-- Name: idx_weather_history_wind; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_weather_history_wind ON public.weather_history USING btree (wind_speed_ms);


--
-- Name: alert_deliveries alert_deliveries_queue_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.alert_deliveries
    ADD CONSTRAINT alert_deliveries_queue_id_fkey FOREIGN KEY (queue_id) REFERENCES public.alert_queue(queue_id);


--
-- Name: alert_queue alert_queue_history_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.alert_queue
    ADD CONSTRAINT alert_queue_history_id_fkey FOREIGN KEY (history_id) REFERENCES public.weather_history(history_id) ON DELETE CASCADE;


--
-- Name: alert_queue alert_queue_location_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.alert_queue
    ADD CONSTRAINT alert_queue_location_id_fkey FOREIGN KEY (location_id) REFERENCES public.locations(location_id) ON DELETE CASCADE;


--
-- Name: alert_queue alert_queue_parent_alert_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.alert_queue
    ADD CONSTRAINT alert_queue_parent_alert_id_fkey FOREIGN KEY (parent_alert_id) REFERENCES public.alert_queue(queue_id);


--
-- Name: alert_queue alert_queue_rule_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.alert_queue
    ADD CONSTRAINT alert_queue_rule_id_fkey FOREIGN KEY (rule_id) REFERENCES public.alert_rules(rule_id) ON DELETE CASCADE;


--
-- Name: alert_rules alert_rules_escalation_rule_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.alert_rules
    ADD CONSTRAINT alert_rules_escalation_rule_id_fkey FOREIGN KEY (escalation_rule_id) REFERENCES public.alert_rules(rule_id);


--
-- Name: alert_rules alert_rules_location_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.alert_rules
    ADD CONSTRAINT alert_rules_location_id_fkey FOREIGN KEY (location_id) REFERENCES public.locations(location_id) ON DELETE CASCADE;


--
-- Name: alert_rules alert_rules_parent_rule_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.alert_rules
    ADD CONSTRAINT alert_rules_parent_rule_id_fkey FOREIGN KEY (parent_rule_id) REFERENCES public.alert_rules(rule_id);


--
-- Name: alert_queue fk_parent_alert; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.alert_queue
    ADD CONSTRAINT fk_parent_alert FOREIGN KEY (parent_alert_id) REFERENCES public.alert_queue(queue_id) ON DELETE SET NULL;


--
-- Name: user_preferences user_preferences_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.user_preferences
    ADD CONSTRAINT user_preferences_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON DELETE CASCADE;


--
-- Name: weather_archive_settings weather_archive_settings_location_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.weather_archive_settings
    ADD CONSTRAINT weather_archive_settings_location_id_fkey FOREIGN KEY (location_id) REFERENCES public.locations(location_id) ON DELETE CASCADE;


--
-- Name: weather_history weather_history_location_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.weather_history
    ADD CONSTRAINT weather_history_location_id_fkey FOREIGN KEY (location_id) REFERENCES public.locations(location_id) ON DELETE CASCADE;


--
-- Name: SCHEMA public; Type: ACL; Schema: -; Owner: pg_database_owner
--

GRANT ALL ON SCHEMA public TO gis_wac_user;


--
-- Name: FUNCTION uuid_generate_v1(); Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON FUNCTION public.uuid_generate_v1() TO gis_wac_user;


--
-- Name: FUNCTION uuid_generate_v1mc(); Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON FUNCTION public.uuid_generate_v1mc() TO gis_wac_user;


--
-- Name: FUNCTION uuid_generate_v3(namespace uuid, name text); Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON FUNCTION public.uuid_generate_v3(namespace uuid, name text) TO gis_wac_user;


--
-- Name: FUNCTION uuid_generate_v4(); Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON FUNCTION public.uuid_generate_v4() TO gis_wac_user;


--
-- Name: FUNCTION uuid_generate_v5(namespace uuid, name text); Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON FUNCTION public.uuid_generate_v5(namespace uuid, name text) TO gis_wac_user;


--
-- Name: FUNCTION uuid_nil(); Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON FUNCTION public.uuid_nil() TO gis_wac_user;


--
-- Name: FUNCTION uuid_ns_dns(); Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON FUNCTION public.uuid_ns_dns() TO gis_wac_user;


--
-- Name: FUNCTION uuid_ns_oid(); Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON FUNCTION public.uuid_ns_oid() TO gis_wac_user;


--
-- Name: FUNCTION uuid_ns_url(); Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON FUNCTION public.uuid_ns_url() TO gis_wac_user;


--
-- Name: FUNCTION uuid_ns_x500(); Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON FUNCTION public.uuid_ns_x500() TO gis_wac_user;


--
-- Name: TABLE alert_deliveries; Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON TABLE public.alert_deliveries TO gis_wac_user;


--
-- Name: SEQUENCE alert_deliveries_delivery_id_seq; Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON SEQUENCE public.alert_deliveries_delivery_id_seq TO gis_wac_user;


--
-- Name: TABLE alert_queue; Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON TABLE public.alert_queue TO gis_wac_user;


--
-- Name: SEQUENCE alert_queue_queue_id_seq; Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON SEQUENCE public.alert_queue_queue_id_seq TO gis_wac_user;


--
-- Name: TABLE alert_rules; Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON TABLE public.alert_rules TO gis_wac_user;


--
-- Name: TABLE locations; Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON TABLE public.locations TO gis_wac_user;


--
-- Name: TABLE system_config; Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON TABLE public.system_config TO gis_wac_user;


--
-- Name: SEQUENCE system_config_config_id_seq; Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON SEQUENCE public.system_config_config_id_seq TO gis_wac_user;


--
-- Name: TABLE user_locations; Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON TABLE public.user_locations TO gis_wac_user;


--
-- Name: TABLE users; Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON TABLE public.users TO gis_wac_user;


--
-- Name: TABLE user_location_details; Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON TABLE public.user_location_details TO gis_wac_user;


--
-- Name: TABLE user_preferences; Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON TABLE public.user_preferences TO gis_wac_user;


--
-- Name: TABLE weather_archive_settings; Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON TABLE public.weather_archive_settings TO gis_wac_user;


--
-- Name: SEQUENCE weather_archive_settings_setting_id_seq; Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON SEQUENCE public.weather_archive_settings_setting_id_seq TO gis_wac_user;


--
-- Name: TABLE weather_history; Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON TABLE public.weather_history TO gis_wac_user;


--
-- Name: SEQUENCE weather_history_history_id_seq; Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON SEQUENCE public.weather_history_history_id_seq TO gis_wac_user;


--
-- Name: DEFAULT PRIVILEGES FOR SEQUENCES; Type: DEFAULT ACL; Schema: public; Owner: postgres
--

ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public GRANT ALL ON SEQUENCES TO gis_wac_user;


--
-- Name: DEFAULT PRIVILEGES FOR TYPES; Type: DEFAULT ACL; Schema: public; Owner: postgres
--

ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public GRANT ALL ON TYPES TO gis_wac_user;


--
-- Name: DEFAULT PRIVILEGES FOR FUNCTIONS; Type: DEFAULT ACL; Schema: public; Owner: postgres
--

ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public GRANT ALL ON FUNCTIONS TO gis_wac_user;


--
-- Name: DEFAULT PRIVILEGES FOR TABLES; Type: DEFAULT ACL; Schema: public; Owner: postgres
--

ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public GRANT ALL ON TABLES TO gis_wac_user;


--
-- PostgreSQL database dump complete
--

\unrestrict JAJp7xjo7UfYSqVf1KMlSmyouCeb2qQmnsCm3395FJUEgwpXWVadiROOLujF5NV

