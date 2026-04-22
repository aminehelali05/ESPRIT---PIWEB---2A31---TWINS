-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 19, 2026 at 07:21 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `projet`
--

-- --------------------------------------------------------

--
-- Table structure for table `call_sessions`
--

CREATE TABLE `call_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `thread_type` enum('private','group') NOT NULL,
  `thread_id` int(11) NOT NULL,
  `caller_id` int(11) NOT NULL,
  `callee_id` int(11) DEFAULT NULL,
  `call_type` enum('audio','video') NOT NULL DEFAULT 'video',
  `status` enum('ringing','accepted','rejected','ended','missed') NOT NULL DEFAULT 'ringing',
  `started_at` datetime DEFAULT NULL,
  `answered_at` datetime DEFAULT NULL,
  `ended_at` datetime DEFAULT NULL,
  `ended_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `call_sessions`
--

INSERT INTO `call_sessions` (`id`, `thread_type`, `thread_id`, `caller_id`, `callee_id`, `call_type`, `status`, `started_at`, `answered_at`, `ended_at`, `ended_by`, `created_at`, `updated_at`) VALUES
(1, 'private', 1, 1, 15, 'video', 'ended', '2026-04-18 21:29:40', NULL, '2026-04-18 21:29:55', 1, '2026-04-18 20:29:40', '2026-04-18 20:29:55'),
(2, 'private', 1, 1, 15, 'video', 'ended', '2026-04-18 21:43:44', NULL, '2026-04-18 21:43:46', 1, '2026-04-18 20:43:44', '2026-04-18 20:43:46'),
(3, 'private', 1, 1, 15, 'video', 'ended', '2026-04-18 21:43:47', '2026-04-18 21:43:56', '2026-04-18 21:44:31', 1, '2026-04-18 20:43:47', '2026-04-18 20:44:31'),
(4, 'private', 1, 1, 15, 'video', 'ended', '2026-04-19 18:02:44', NULL, '2026-04-19 18:02:49', 1, '2026-04-19 17:02:44', '2026-04-19 17:02:49'),
(5, 'private', 1, 1, 15, 'audio', 'ended', '2026-04-19 18:04:58', NULL, '2026-04-19 18:05:33', 1, '2026-04-19 17:04:58', '2026-04-19 17:05:33');

-- --------------------------------------------------------

--
-- Table structure for table `call_signals`
--

CREATE TABLE `call_signals` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `session_id` bigint(20) UNSIGNED NOT NULL,
  `sender_id` int(11) NOT NULL,
  `signal_type` enum('offer','answer','candidate','renegotiate','bye') NOT NULL,
  `payload` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `call_signals`
--

INSERT INTO `call_signals` (`id`, `session_id`, `sender_id`, `signal_type`, `payload`, `created_at`) VALUES
(1, 1, 1, 'offer', '{\"sdp\":\"v=0\\r\\no=- 1391805877894494286 2 IN IP4 127.0.0.1\\r\\ns=-\\r\\nt=0 0\\r\\na=group:BUNDLE 0 1\\r\\na=extmap-allow-mixed\\r\\na=msid-semantic: WMS 51213409-f347-427e-9f88-5c0326c52811\\r\\nm=audio 9 UDP\\/TLS\\/RTP\\/SAVPF 111 63 9 0 8 13 110 126\\r\\nc=IN IP4 0.0.0.0\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=ice-ufrag:fGv\\/\\r\\na=ice-pwd:6VbSPFf+LmvOvhqYydpYlPtb\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 CE:46:17:78:04:B6:7A:9A:36:E2:9B:39:94:F7:53:7B:48:FD:A8:6F:C7:CF:5E:BE:9E:0A:32:D3:6A:6C:33:E9\\r\\na=setup:actpass\\r\\na=mid:0\\r\\na=extmap:1 urn:ietf:params:rtp-hdrext:ssrc-audio-level\\r\\na=extmap:2 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/abs-send-time\\r\\na=extmap:3 http:\\/\\/www.ietf.org\\/id\\/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=sendrecv\\r\\na=msid:51213409-f347-427e-9f88-5c0326c52811 bcbe46c9-8a35-436c-b7a0-103605630b85\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:111 opus\\/48000\\/2\\r\\na=rtcp-fb:111 transport-cc\\r\\na=fmtp:111 minptime=10;useinbandfec=1\\r\\na=rtpmap:63 red\\/48000\\/2\\r\\na=fmtp:63 111\\/111\\r\\na=rtpmap:9 G722\\/8000\\r\\na=rtpmap:0 PCMU\\/8000\\r\\na=rtpmap:8 PCMA\\/8000\\r\\na=rtpmap:13 CN\\/8000\\r\\na=rtpmap:110 telephone-event\\/48000\\r\\na=rtpmap:126 telephone-event\\/8000\\r\\na=ssrc:3669490184 cname:+wMxN30RSNsxfryl\\r\\na=ssrc:3669490184 msid:51213409-f347-427e-9f88-5c0326c52811 bcbe46c9-8a35-436c-b7a0-103605630b85\\r\\nm=video 9 UDP\\/TLS\\/RTP\\/SAVPF 96 97 103 104 107 108 109 114 115 116 117 118 39 40 45 46 98 99 100 101 119 120 49 50 51 52 123 124 125\\r\\nc=IN IP4 0.0.0.0\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=ice-ufrag:fGv\\/\\r\\na=ice-pwd:6VbSPFf+LmvOvhqYydpYlPtb\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 CE:46:17:78:04:B6:7A:9A:36:E2:9B:39:94:F7:53:7B:48:FD:A8:6F:C7:CF:5E:BE:9E:0A:32:D3:6A:6C:33:E9\\r\\na=setup:actpass\\r\\na=mid:1\\r\\na=extmap:14 urn:ietf:params:rtp-hdrext:toffset\\r\\na=extmap:2 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/abs-send-time\\r\\na=extmap:13 urn:3gpp:video-orientation\\r\\na=extmap:3 http:\\/\\/www.ietf.org\\/id\\/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:5 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/playout-delay\\r\\na=extmap:6 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/video-content-type\\r\\na=extmap:7 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/video-timing\\r\\na=extmap:8 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/color-space\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=extmap:10 urn:ietf:params:rtp-hdrext:sdes:rtp-stream-id\\r\\na=extmap:11 urn:ietf:params:rtp-hdrext:sdes:repaired-rtp-stream-id\\r\\na=sendrecv\\r\\na=msid:51213409-f347-427e-9f88-5c0326c52811 1c99bfa6-b0e9-4aff-8a71-5a2148e6ebe7\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:96 VP8\\/90000\\r\\na=rtcp-fb:96 goog-remb\\r\\na=rtcp-fb:96 transport-cc\\r\\na=rtcp-fb:96 ccm fir\\r\\na=rtcp-fb:96 nack\\r\\na=rtcp-fb:96 nack pli\\r\\na=rtpmap:97 rtx\\/90000\\r\\na=fmtp:97 apt=96\\r\\na=rtpmap:103 H264\\/90000\\r\\na=rtcp-fb:103 goog-remb\\r\\na=rtcp-fb:103 transport-cc\\r\\na=rtcp-fb:103 ccm fir\\r\\na=rtcp-fb:103 nack\\r\\na=rtcp-fb:103 nack pli\\r\\na=fmtp:103 level-asymmetry-allowed=1;packetization-mode=1;profile-level-id=42001f\\r\\na=rtpmap:104 rtx\\/90000\\r\\na=fmtp:104 apt=103\\r\\na=rtpmap:107 H264\\/90000\\r\\na=rtcp-fb:107 goog-remb\\r\\na=rtcp-fb:107 transport-cc\\r\\na=rtcp-fb:107 ccm fir\\r\\na=rtcp-fb:107 nack\\r\\na=rtcp-fb:107 nack pli\\r\\na=fmtp:107 level-asymmetry-allowed=1;packetization-mode=0;profile-level-id=42001f\\r\\na=rtpmap:108 rtx\\/90000\\r\\na=fmtp:108 apt=107\\r\\na=rtpmap:109 H264\\/90000\\r\\na=rtcp-fb:109 goog-remb\\r\\na=rtcp-fb:109 transport-cc\\r\\na=rtcp-fb:109 ccm fir\\r\\na=rtcp-fb:109 nack\\r\\na=rtcp-fb:109 nack pli\\r\\na=fmtp:109 level-asymmetry-allowed=1;packetization-mode=1;profile-level-id=42e01f\\r\\na=rtpmap:114 rtx\\/90000\\r\\na=fmtp:114 apt=109\\r\\na=rtpmap:115 H264\\/90000\\r\\na=rtcp-fb:115 goog-remb\\r\\na=rtcp-fb:115 transport-cc\\r\\na=rtcp-fb:115 ccm fir\\r\\na=rtcp-fb:115 nack\\r\\na=rtcp-fb:115 nack pli\\r\\na=fmtp:115 level-asymmetry-allowed=1;packetization-mode=0;profile-level-id=42e01f\\r\\na=rtpmap:116 rtx\\/90000\\r\\na=fmtp:116 apt=115\\r\\na=rtpmap:117 H264\\/90000\\r\\na=rtcp-fb:117 goog-remb\\r\\na=rtcp-fb:117 transport-cc\\r\\na=rtcp-fb:117 ccm fir\\r\\na=rtcp-fb:117 nack\\r\\na=rtcp-fb:117 nack pli\\r\\na=fmtp:117 level-asymmetry-allowed=1;packetization-mode=1;profile-level-id=4d001f\\r\\na=rtpmap:118 rtx\\/90000\\r\\na=fmtp:118 apt=117\\r\\na=rtpmap:39 H264\\/90000\\r\\na=rtcp-fb:39 goog-remb\\r\\na=rtcp-fb:39 transport-cc\\r\\na=rtcp-fb:39 ccm fir\\r\\na=rtcp-fb:39 nack\\r\\na=rtcp-fb:39 nack pli\\r\\na=fmtp:39 level-asymmetry-allowed=1;packetization-mode=0;profile-level-id=4d001f\\r\\na=rtpmap:40 rtx\\/90000\\r\\na=fmtp:40 apt=39\\r\\na=rtpmap:45 AV1\\/90000\\r\\na=rtcp-fb:45 goog-remb\\r\\na=rtcp-fb:45 transport-cc\\r\\na=rtcp-fb:45 ccm fir\\r\\na=rtcp-fb:45 nack\\r\\na=rtcp-fb:45 nack pli\\r\\na=fmtp:45 level-idx=5;profile=0;tier=0\\r\\na=rtpmap:46 rtx\\/90000\\r\\na=fmtp:46 apt=45\\r\\na=rtpmap:98 VP9\\/90000\\r\\na=rtcp-fb:98 goog-remb\\r\\na=rtcp-fb:98 transport-cc\\r\\na=rtcp-fb:98 ccm fir\\r\\na=rtcp-fb:98 nack\\r\\na=rtcp-fb:98 nack pli\\r\\na=fmtp:98 profile-id=0\\r\\na=rtpmap:99 rtx\\/90000\\r\\na=fmtp:99 apt=98\\r\\na=rtpmap:100 VP9\\/90000\\r\\na=rtcp-fb:100 goog-remb\\r\\na=rtcp-fb:100 transport-cc\\r\\na=rtcp-fb:100 ccm fir\\r\\na=rtcp-fb:100 nack\\r\\na=rtcp-fb:100 nack pli\\r\\na=fmtp:100 profile-id=2\\r\\na=rtpmap:101 rtx\\/90000\\r\\na=fmtp:101 apt=100\\r\\na=rtpmap:119 H264\\/90000\\r\\na=rtcp-fb:119 goog-remb\\r\\na=rtcp-fb:119 transport-cc\\r\\na=rtcp-fb:119 ccm fir\\r\\na=rtcp-fb:119 nack\\r\\na=rtcp-fb:119 nack pli\\r\\na=fmtp:119 level-asymmetry-allowed=1;packetization-mode=1;profile-level-id=64001f\\r\\na=rtpmap:120 rtx\\/90000\\r\\na=fmtp:120 apt=119\\r\\na=rtpmap:49 H265\\/90000\\r\\na=rtcp-fb:49 goog-remb\\r\\na=rtcp-fb:49 transport-cc\\r\\na=rtcp-fb:49 ccm fir\\r\\na=rtcp-fb:49 nack\\r\\na=rtcp-fb:49 nack pli\\r\\na=fmtp:49 level-id=180;profile-id=1;tier-flag=0;tx-mode=SRST\\r\\na=rtpmap:50 rtx\\/90000\\r\\na=fmtp:50 apt=49\\r\\na=rtpmap:51 H265\\/90000\\r\\na=rtcp-fb:51 goog-remb\\r\\na=rtcp-fb:51 transport-cc\\r\\na=rtcp-fb:51 ccm fir\\r\\na=rtcp-fb:51 nack\\r\\na=rtcp-fb:51 nack pli\\r\\na=fmtp:51 level-id=180;profile-id=2;tier-flag=0;tx-mode=SRST\\r\\na=rtpmap:52 rtx\\/90000\\r\\na=fmtp:52 apt=51\\r\\na=rtpmap:123 red\\/90000\\r\\na=rtpmap:124 rtx\\/90000\\r\\na=fmtp:124 apt=123\\r\\na=rtpmap:125 ulpfec\\/90000\\r\\na=ssrc-group:FID 1547726387 3144237\\r\\na=ssrc:1547726387 cname:+wMxN30RSNsxfryl\\r\\na=ssrc:1547726387 msid:51213409-f347-427e-9f88-5c0326c52811 1c99bfa6-b0e9-4aff-8a71-5a2148e6ebe7\\r\\na=ssrc:3144237 cname:+wMxN30RSNsxfryl\\r\\na=ssrc:3144237 msid:51213409-f347-427e-9f88-5c0326c52811 1c99bfa6-b0e9-4aff-8a71-5a2148e6ebe7\\r\\n\",\"type\":\"offer\"}', '2026-04-18 20:29:40'),
(2, 1, 1, 'bye', '{\"reason\":\"Ended by participant\"}', '2026-04-18 20:29:55'),
(3, 2, 1, 'offer', '{\"sdp\":\"v=0\\r\\no=- 7157469136653654040 2 IN IP4 127.0.0.1\\r\\ns=-\\r\\nt=0 0\\r\\na=group:BUNDLE 0 1\\r\\na=extmap-allow-mixed\\r\\na=msid-semantic: WMS 18298106-f47a-4d62-b7a8-9d95822dfc34\\r\\nm=audio 9 UDP\\/TLS\\/RTP\\/SAVPF 111 63 9 0 8 13 110 126\\r\\nc=IN IP4 0.0.0.0\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=ice-ufrag:ZfS3\\r\\na=ice-pwd:BfAR4LYvGOzzd2O2LicapVe3\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 18:9B:B2:FD:C6:03:B9:C1:C5:85:8C:8F:51:2F:8A:81:F1:5C:09:9E:02:15:A8:63:97:74:C3:05:A4:32:31:CA\\r\\na=setup:actpass\\r\\na=mid:0\\r\\na=extmap:1 urn:ietf:params:rtp-hdrext:ssrc-audio-level\\r\\na=extmap:2 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/abs-send-time\\r\\na=extmap:3 http:\\/\\/www.ietf.org\\/id\\/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=sendrecv\\r\\na=msid:18298106-f47a-4d62-b7a8-9d95822dfc34 4b8b35eb-e474-47e1-97ea-82d5d7d731b1\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:111 opus\\/48000\\/2\\r\\na=rtcp-fb:111 transport-cc\\r\\na=fmtp:111 minptime=10;useinbandfec=1\\r\\na=rtpmap:63 red\\/48000\\/2\\r\\na=fmtp:63 111\\/111\\r\\na=rtpmap:9 G722\\/8000\\r\\na=rtpmap:0 PCMU\\/8000\\r\\na=rtpmap:8 PCMA\\/8000\\r\\na=rtpmap:13 CN\\/8000\\r\\na=rtpmap:110 telephone-event\\/48000\\r\\na=rtpmap:126 telephone-event\\/8000\\r\\na=ssrc:3109519815 cname:ianOHN8OEyMHvjrw\\r\\na=ssrc:3109519815 msid:18298106-f47a-4d62-b7a8-9d95822dfc34 4b8b35eb-e474-47e1-97ea-82d5d7d731b1\\r\\nm=video 9 UDP\\/TLS\\/RTP\\/SAVPF 96 97 103 104 107 108 109 114 115 116 117 118 39 40 45 46 98 99 100 101 119 120 49 50 51 52 123 124 125\\r\\nc=IN IP4 0.0.0.0\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=ice-ufrag:ZfS3\\r\\na=ice-pwd:BfAR4LYvGOzzd2O2LicapVe3\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 18:9B:B2:FD:C6:03:B9:C1:C5:85:8C:8F:51:2F:8A:81:F1:5C:09:9E:02:15:A8:63:97:74:C3:05:A4:32:31:CA\\r\\na=setup:actpass\\r\\na=mid:1\\r\\na=extmap:14 urn:ietf:params:rtp-hdrext:toffset\\r\\na=extmap:2 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/abs-send-time\\r\\na=extmap:13 urn:3gpp:video-orientation\\r\\na=extmap:3 http:\\/\\/www.ietf.org\\/id\\/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:5 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/playout-delay\\r\\na=extmap:6 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/video-content-type\\r\\na=extmap:7 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/video-timing\\r\\na=extmap:8 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/color-space\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=extmap:10 urn:ietf:params:rtp-hdrext:sdes:rtp-stream-id\\r\\na=extmap:11 urn:ietf:params:rtp-hdrext:sdes:repaired-rtp-stream-id\\r\\na=sendrecv\\r\\na=msid:18298106-f47a-4d62-b7a8-9d95822dfc34 7e6f3f38-15e6-4654-9c54-90fb9d1acae5\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:96 VP8\\/90000\\r\\na=rtcp-fb:96 goog-remb\\r\\na=rtcp-fb:96 transport-cc\\r\\na=rtcp-fb:96 ccm fir\\r\\na=rtcp-fb:96 nack\\r\\na=rtcp-fb:96 nack pli\\r\\na=rtpmap:97 rtx\\/90000\\r\\na=fmtp:97 apt=96\\r\\na=rtpmap:103 H264\\/90000\\r\\na=rtcp-fb:103 goog-remb\\r\\na=rtcp-fb:103 transport-cc\\r\\na=rtcp-fb:103 ccm fir\\r\\na=rtcp-fb:103 nack\\r\\na=rtcp-fb:103 nack pli\\r\\na=fmtp:103 level-asymmetry-allowed=1;packetization-mode=1;profile-level-id=42001f\\r\\na=rtpmap:104 rtx\\/90000\\r\\na=fmtp:104 apt=103\\r\\na=rtpmap:107 H264\\/90000\\r\\na=rtcp-fb:107 goog-remb\\r\\na=rtcp-fb:107 transport-cc\\r\\na=rtcp-fb:107 ccm fir\\r\\na=rtcp-fb:107 nack\\r\\na=rtcp-fb:107 nack pli\\r\\na=fmtp:107 level-asymmetry-allowed=1;packetization-mode=0;profile-level-id=42001f\\r\\na=rtpmap:108 rtx\\/90000\\r\\na=fmtp:108 apt=107\\r\\na=rtpmap:109 H264\\/90000\\r\\na=rtcp-fb:109 goog-remb\\r\\na=rtcp-fb:109 transport-cc\\r\\na=rtcp-fb:109 ccm fir\\r\\na=rtcp-fb:109 nack\\r\\na=rtcp-fb:109 nack pli\\r\\na=fmtp:109 level-asymmetry-allowed=1;packetization-mode=1;profile-level-id=42e01f\\r\\na=rtpmap:114 rtx\\/90000\\r\\na=fmtp:114 apt=109\\r\\na=rtpmap:115 H264\\/90000\\r\\na=rtcp-fb:115 goog-remb\\r\\na=rtcp-fb:115 transport-cc\\r\\na=rtcp-fb:115 ccm fir\\r\\na=rtcp-fb:115 nack\\r\\na=rtcp-fb:115 nack pli\\r\\na=fmtp:115 level-asymmetry-allowed=1;packetization-mode=0;profile-level-id=42e01f\\r\\na=rtpmap:116 rtx\\/90000\\r\\na=fmtp:116 apt=115\\r\\na=rtpmap:117 H264\\/90000\\r\\na=rtcp-fb:117 goog-remb\\r\\na=rtcp-fb:117 transport-cc\\r\\na=rtcp-fb:117 ccm fir\\r\\na=rtcp-fb:117 nack\\r\\na=rtcp-fb:117 nack pli\\r\\na=fmtp:117 level-asymmetry-allowed=1;packetization-mode=1;profile-level-id=4d001f\\r\\na=rtpmap:118 rtx\\/90000\\r\\na=fmtp:118 apt=117\\r\\na=rtpmap:39 H264\\/90000\\r\\na=rtcp-fb:39 goog-remb\\r\\na=rtcp-fb:39 transport-cc\\r\\na=rtcp-fb:39 ccm fir\\r\\na=rtcp-fb:39 nack\\r\\na=rtcp-fb:39 nack pli\\r\\na=fmtp:39 level-asymmetry-allowed=1;packetization-mode=0;profile-level-id=4d001f\\r\\na=rtpmap:40 rtx\\/90000\\r\\na=fmtp:40 apt=39\\r\\na=rtpmap:45 AV1\\/90000\\r\\na=rtcp-fb:45 goog-remb\\r\\na=rtcp-fb:45 transport-cc\\r\\na=rtcp-fb:45 ccm fir\\r\\na=rtcp-fb:45 nack\\r\\na=rtcp-fb:45 nack pli\\r\\na=fmtp:45 level-idx=5;profile=0;tier=0\\r\\na=rtpmap:46 rtx\\/90000\\r\\na=fmtp:46 apt=45\\r\\na=rtpmap:98 VP9\\/90000\\r\\na=rtcp-fb:98 goog-remb\\r\\na=rtcp-fb:98 transport-cc\\r\\na=rtcp-fb:98 ccm fir\\r\\na=rtcp-fb:98 nack\\r\\na=rtcp-fb:98 nack pli\\r\\na=fmtp:98 profile-id=0\\r\\na=rtpmap:99 rtx\\/90000\\r\\na=fmtp:99 apt=98\\r\\na=rtpmap:100 VP9\\/90000\\r\\na=rtcp-fb:100 goog-remb\\r\\na=rtcp-fb:100 transport-cc\\r\\na=rtcp-fb:100 ccm fir\\r\\na=rtcp-fb:100 nack\\r\\na=rtcp-fb:100 nack pli\\r\\na=fmtp:100 profile-id=2\\r\\na=rtpmap:101 rtx\\/90000\\r\\na=fmtp:101 apt=100\\r\\na=rtpmap:119 H264\\/90000\\r\\na=rtcp-fb:119 goog-remb\\r\\na=rtcp-fb:119 transport-cc\\r\\na=rtcp-fb:119 ccm fir\\r\\na=rtcp-fb:119 nack\\r\\na=rtcp-fb:119 nack pli\\r\\na=fmtp:119 level-asymmetry-allowed=1;packetization-mode=1;profile-level-id=64001f\\r\\na=rtpmap:120 rtx\\/90000\\r\\na=fmtp:120 apt=119\\r\\na=rtpmap:49 H265\\/90000\\r\\na=rtcp-fb:49 goog-remb\\r\\na=rtcp-fb:49 transport-cc\\r\\na=rtcp-fb:49 ccm fir\\r\\na=rtcp-fb:49 nack\\r\\na=rtcp-fb:49 nack pli\\r\\na=fmtp:49 level-id=180;profile-id=1;tier-flag=0;tx-mode=SRST\\r\\na=rtpmap:50 rtx\\/90000\\r\\na=fmtp:50 apt=49\\r\\na=rtpmap:51 H265\\/90000\\r\\na=rtcp-fb:51 goog-remb\\r\\na=rtcp-fb:51 transport-cc\\r\\na=rtcp-fb:51 ccm fir\\r\\na=rtcp-fb:51 nack\\r\\na=rtcp-fb:51 nack pli\\r\\na=fmtp:51 level-id=180;profile-id=2;tier-flag=0;tx-mode=SRST\\r\\na=rtpmap:52 rtx\\/90000\\r\\na=fmtp:52 apt=51\\r\\na=rtpmap:123 red\\/90000\\r\\na=rtpmap:124 rtx\\/90000\\r\\na=fmtp:124 apt=123\\r\\na=rtpmap:125 ulpfec\\/90000\\r\\na=ssrc-group:FID 3332618515 1128154663\\r\\na=ssrc:3332618515 cname:ianOHN8OEyMHvjrw\\r\\na=ssrc:3332618515 msid:18298106-f47a-4d62-b7a8-9d95822dfc34 7e6f3f38-15e6-4654-9c54-90fb9d1acae5\\r\\na=ssrc:1128154663 cname:ianOHN8OEyMHvjrw\\r\\na=ssrc:1128154663 msid:18298106-f47a-4d62-b7a8-9d95822dfc34 7e6f3f38-15e6-4654-9c54-90fb9d1acae5\\r\\n\",\"type\":\"offer\"}', '2026-04-18 20:43:44'),
(4, 2, 1, 'candidate', '{\"candidate\":\"candidate:1606996517 1 udp 1685921535 197.25.18.10 1030 typ srflx raddr 192.168.1.223 rport 63625 generation 0 ufrag ZfS3 network-id 2 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"ZfS3\"}', '2026-04-18 20:43:44'),
(5, 2, 1, 'candidate', '{\"candidate\":\"candidate:744678745 1 tcp 1518280447 192.168.127.1 9 typ host tcptype active generation 0 ufrag ZfS3 network-id 1\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"ZfS3\"}', '2026-04-18 20:43:44'),
(6, 2, 1, 'candidate', '{\"candidate\":\"candidate:3209770406 1 tcp 1518214911 192.168.204.1 9 typ host tcptype active generation 0 ufrag ZfS3 network-id 3\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"ZfS3\"}', '2026-04-18 20:43:44'),
(7, 2, 1, 'candidate', '{\"candidate\":\"candidate:2387464579 1 tcp 1518149375 192.168.1.223 9 typ host tcptype active generation 0 ufrag ZfS3 network-id 2 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"ZfS3\"}', '2026-04-18 20:43:44'),
(8, 2, 1, 'candidate', '{\"candidate\":\"candidate:744678745 1 tcp 1518280447 192.168.127.1 9 typ host tcptype active generation 0 ufrag ZfS3 network-id 1\",\"sdpMid\":\"1\",\"sdpMLineIndex\":1,\"usernameFragment\":\"ZfS3\"}', '2026-04-18 20:43:44'),
(9, 2, 1, 'candidate', '{\"candidate\":\"candidate:3209770406 1 tcp 1518214911 192.168.204.1 9 typ host tcptype active generation 0 ufrag ZfS3 network-id 3\",\"sdpMid\":\"1\",\"sdpMLineIndex\":1,\"usernameFragment\":\"ZfS3\"}', '2026-04-18 20:43:44'),
(10, 2, 1, 'candidate', '{\"candidate\":\"candidate:2387464579 1 tcp 1518149375 192.168.1.223 9 typ host tcptype active generation 0 ufrag ZfS3 network-id 2 network-cost 10\",\"sdpMid\":\"1\",\"sdpMLineIndex\":1,\"usernameFragment\":\"ZfS3\"}', '2026-04-18 20:43:44'),
(11, 2, 1, 'candidate', '{\"candidate\":\"candidate:1606996517 1 udp 1685921535 197.25.18.10 1031 typ srflx raddr 192.168.1.223 rport 63628 generation 0 ufrag ZfS3 network-id 2 network-cost 10\",\"sdpMid\":\"1\",\"sdpMLineIndex\":1,\"usernameFragment\":\"ZfS3\"}', '2026-04-18 20:43:44'),
(12, 2, 1, 'bye', '{\"reason\":\"Ended by participant\"}', '2026-04-18 20:43:46'),
(13, 3, 1, 'offer', '{\"sdp\":\"v=0\\r\\no=- 2057627987598010756 2 IN IP4 127.0.0.1\\r\\ns=-\\r\\nt=0 0\\r\\na=group:BUNDLE 0 1\\r\\na=extmap-allow-mixed\\r\\na=msid-semantic: WMS 3f6e9167-bc11-4253-a332-56441f773202\\r\\nm=audio 9 UDP\\/TLS\\/RTP\\/SAVPF 111 63 9 0 8 13 110 126\\r\\nc=IN IP4 0.0.0.0\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=ice-ufrag:45IO\\r\\na=ice-pwd:lugiGCI0w7OnpuNtCVTC8mzQ\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 61:7B:1E:01:BF:EB:D9:09:5A:3C:AC:A5:4C:28:09:91:F4:64:3C:CE:D4:6A:71:32:38:E4:EB:89:85:13:06:10\\r\\na=setup:actpass\\r\\na=mid:0\\r\\na=extmap:1 urn:ietf:params:rtp-hdrext:ssrc-audio-level\\r\\na=extmap:2 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/abs-send-time\\r\\na=extmap:3 http:\\/\\/www.ietf.org\\/id\\/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=sendrecv\\r\\na=msid:3f6e9167-bc11-4253-a332-56441f773202 ba65afcd-679b-4cb1-8a54-a3c2d6b7e31a\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:111 opus\\/48000\\/2\\r\\na=rtcp-fb:111 transport-cc\\r\\na=fmtp:111 minptime=10;useinbandfec=1\\r\\na=rtpmap:63 red\\/48000\\/2\\r\\na=fmtp:63 111\\/111\\r\\na=rtpmap:9 G722\\/8000\\r\\na=rtpmap:0 PCMU\\/8000\\r\\na=rtpmap:8 PCMA\\/8000\\r\\na=rtpmap:13 CN\\/8000\\r\\na=rtpmap:110 telephone-event\\/48000\\r\\na=rtpmap:126 telephone-event\\/8000\\r\\na=ssrc:3546413611 cname:7fdE+FHan1\\/ySs02\\r\\na=ssrc:3546413611 msid:3f6e9167-bc11-4253-a332-56441f773202 ba65afcd-679b-4cb1-8a54-a3c2d6b7e31a\\r\\nm=video 9 UDP\\/TLS\\/RTP\\/SAVPF 96 97 103 104 107 108 109 114 115 116 117 118 39 40 45 46 98 99 100 101 119 120 49 50 51 52 123 124 125\\r\\nc=IN IP4 0.0.0.0\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=ice-ufrag:45IO\\r\\na=ice-pwd:lugiGCI0w7OnpuNtCVTC8mzQ\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 61:7B:1E:01:BF:EB:D9:09:5A:3C:AC:A5:4C:28:09:91:F4:64:3C:CE:D4:6A:71:32:38:E4:EB:89:85:13:06:10\\r\\na=setup:actpass\\r\\na=mid:1\\r\\na=extmap:14 urn:ietf:params:rtp-hdrext:toffset\\r\\na=extmap:2 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/abs-send-time\\r\\na=extmap:13 urn:3gpp:video-orientation\\r\\na=extmap:3 http:\\/\\/www.ietf.org\\/id\\/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:5 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/playout-delay\\r\\na=extmap:6 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/video-content-type\\r\\na=extmap:7 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/video-timing\\r\\na=extmap:8 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/color-space\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=extmap:10 urn:ietf:params:rtp-hdrext:sdes:rtp-stream-id\\r\\na=extmap:11 urn:ietf:params:rtp-hdrext:sdes:repaired-rtp-stream-id\\r\\na=sendrecv\\r\\na=msid:3f6e9167-bc11-4253-a332-56441f773202 35478045-6e1a-4330-bfa4-bbc1fffc6475\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:96 VP8\\/90000\\r\\na=rtcp-fb:96 goog-remb\\r\\na=rtcp-fb:96 transport-cc\\r\\na=rtcp-fb:96 ccm fir\\r\\na=rtcp-fb:96 nack\\r\\na=rtcp-fb:96 nack pli\\r\\na=rtpmap:97 rtx\\/90000\\r\\na=fmtp:97 apt=96\\r\\na=rtpmap:103 H264\\/90000\\r\\na=rtcp-fb:103 goog-remb\\r\\na=rtcp-fb:103 transport-cc\\r\\na=rtcp-fb:103 ccm fir\\r\\na=rtcp-fb:103 nack\\r\\na=rtcp-fb:103 nack pli\\r\\na=fmtp:103 level-asymmetry-allowed=1;packetization-mode=1;profile-level-id=42001f\\r\\na=rtpmap:104 rtx\\/90000\\r\\na=fmtp:104 apt=103\\r\\na=rtpmap:107 H264\\/90000\\r\\na=rtcp-fb:107 goog-remb\\r\\na=rtcp-fb:107 transport-cc\\r\\na=rtcp-fb:107 ccm fir\\r\\na=rtcp-fb:107 nack\\r\\na=rtcp-fb:107 nack pli\\r\\na=fmtp:107 level-asymmetry-allowed=1;packetization-mode=0;profile-level-id=42001f\\r\\na=rtpmap:108 rtx\\/90000\\r\\na=fmtp:108 apt=107\\r\\na=rtpmap:109 H264\\/90000\\r\\na=rtcp-fb:109 goog-remb\\r\\na=rtcp-fb:109 transport-cc\\r\\na=rtcp-fb:109 ccm fir\\r\\na=rtcp-fb:109 nack\\r\\na=rtcp-fb:109 nack pli\\r\\na=fmtp:109 level-asymmetry-allowed=1;packetization-mode=1;profile-level-id=42e01f\\r\\na=rtpmap:114 rtx\\/90000\\r\\na=fmtp:114 apt=109\\r\\na=rtpmap:115 H264\\/90000\\r\\na=rtcp-fb:115 goog-remb\\r\\na=rtcp-fb:115 transport-cc\\r\\na=rtcp-fb:115 ccm fir\\r\\na=rtcp-fb:115 nack\\r\\na=rtcp-fb:115 nack pli\\r\\na=fmtp:115 level-asymmetry-allowed=1;packetization-mode=0;profile-level-id=42e01f\\r\\na=rtpmap:116 rtx\\/90000\\r\\na=fmtp:116 apt=115\\r\\na=rtpmap:117 H264\\/90000\\r\\na=rtcp-fb:117 goog-remb\\r\\na=rtcp-fb:117 transport-cc\\r\\na=rtcp-fb:117 ccm fir\\r\\na=rtcp-fb:117 nack\\r\\na=rtcp-fb:117 nack pli\\r\\na=fmtp:117 level-asymmetry-allowed=1;packetization-mode=1;profile-level-id=4d001f\\r\\na=rtpmap:118 rtx\\/90000\\r\\na=fmtp:118 apt=117\\r\\na=rtpmap:39 H264\\/90000\\r\\na=rtcp-fb:39 goog-remb\\r\\na=rtcp-fb:39 transport-cc\\r\\na=rtcp-fb:39 ccm fir\\r\\na=rtcp-fb:39 nack\\r\\na=rtcp-fb:39 nack pli\\r\\na=fmtp:39 level-asymmetry-allowed=1;packetization-mode=0;profile-level-id=4d001f\\r\\na=rtpmap:40 rtx\\/90000\\r\\na=fmtp:40 apt=39\\r\\na=rtpmap:45 AV1\\/90000\\r\\na=rtcp-fb:45 goog-remb\\r\\na=rtcp-fb:45 transport-cc\\r\\na=rtcp-fb:45 ccm fir\\r\\na=rtcp-fb:45 nack\\r\\na=rtcp-fb:45 nack pli\\r\\na=fmtp:45 level-idx=5;profile=0;tier=0\\r\\na=rtpmap:46 rtx\\/90000\\r\\na=fmtp:46 apt=45\\r\\na=rtpmap:98 VP9\\/90000\\r\\na=rtcp-fb:98 goog-remb\\r\\na=rtcp-fb:98 transport-cc\\r\\na=rtcp-fb:98 ccm fir\\r\\na=rtcp-fb:98 nack\\r\\na=rtcp-fb:98 nack pli\\r\\na=fmtp:98 profile-id=0\\r\\na=rtpmap:99 rtx\\/90000\\r\\na=fmtp:99 apt=98\\r\\na=rtpmap:100 VP9\\/90000\\r\\na=rtcp-fb:100 goog-remb\\r\\na=rtcp-fb:100 transport-cc\\r\\na=rtcp-fb:100 ccm fir\\r\\na=rtcp-fb:100 nack\\r\\na=rtcp-fb:100 nack pli\\r\\na=fmtp:100 profile-id=2\\r\\na=rtpmap:101 rtx\\/90000\\r\\na=fmtp:101 apt=100\\r\\na=rtpmap:119 H264\\/90000\\r\\na=rtcp-fb:119 goog-remb\\r\\na=rtcp-fb:119 transport-cc\\r\\na=rtcp-fb:119 ccm fir\\r\\na=rtcp-fb:119 nack\\r\\na=rtcp-fb:119 nack pli\\r\\na=fmtp:119 level-asymmetry-allowed=1;packetization-mode=1;profile-level-id=64001f\\r\\na=rtpmap:120 rtx\\/90000\\r\\na=fmtp:120 apt=119\\r\\na=rtpmap:49 H265\\/90000\\r\\na=rtcp-fb:49 goog-remb\\r\\na=rtcp-fb:49 transport-cc\\r\\na=rtcp-fb:49 ccm fir\\r\\na=rtcp-fb:49 nack\\r\\na=rtcp-fb:49 nack pli\\r\\na=fmtp:49 level-id=180;profile-id=1;tier-flag=0;tx-mode=SRST\\r\\na=rtpmap:50 rtx\\/90000\\r\\na=fmtp:50 apt=49\\r\\na=rtpmap:51 H265\\/90000\\r\\na=rtcp-fb:51 goog-remb\\r\\na=rtcp-fb:51 transport-cc\\r\\na=rtcp-fb:51 ccm fir\\r\\na=rtcp-fb:51 nack\\r\\na=rtcp-fb:51 nack pli\\r\\na=fmtp:51 level-id=180;profile-id=2;tier-flag=0;tx-mode=SRST\\r\\na=rtpmap:52 rtx\\/90000\\r\\na=fmtp:52 apt=51\\r\\na=rtpmap:123 red\\/90000\\r\\na=rtpmap:124 rtx\\/90000\\r\\na=fmtp:124 apt=123\\r\\na=rtpmap:125 ulpfec\\/90000\\r\\na=ssrc-group:FID 4175855291 1950003012\\r\\na=ssrc:4175855291 cname:7fdE+FHan1\\/ySs02\\r\\na=ssrc:4175855291 msid:3f6e9167-bc11-4253-a332-56441f773202 35478045-6e1a-4330-bfa4-bbc1fffc6475\\r\\na=ssrc:1950003012 cname:7fdE+FHan1\\/ySs02\\r\\na=ssrc:1950003012 msid:3f6e9167-bc11-4253-a332-56441f773202 35478045-6e1a-4330-bfa4-bbc1fffc6475\\r\\n\",\"type\":\"offer\"}', '2026-04-18 20:43:47'),
(14, 3, 1, 'candidate', '{\"candidate\":\"candidate:2145556658 1 udp 1685921535 197.25.18.10 1032 typ srflx raddr 192.168.1.223 rport 62458 generation 0 ufrag 45IO network-id 2 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"45IO\"}', '2026-04-18 20:43:47'),
(15, 3, 1, 'candidate', '{\"candidate\":\"candidate:2145556658 1 udp 1685921535 197.25.18.10 1033 typ srflx raddr 192.168.1.223 rport 62461 generation 0 ufrag 45IO network-id 2 network-cost 10\",\"sdpMid\":\"1\",\"sdpMLineIndex\":1,\"usernameFragment\":\"45IO\"}', '2026-04-18 20:43:47'),
(16, 3, 1, 'candidate', '{\"candidate\":\"candidate:206090190 1 tcp 1518280447 192.168.127.1 9 typ host tcptype active generation 0 ufrag 45IO network-id 1\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"45IO\"}', '2026-04-18 20:43:47'),
(17, 3, 1, 'candidate', '{\"candidate\":\"candidate:2675670833 1 tcp 1518214911 192.168.204.1 9 typ host tcptype active generation 0 ufrag 45IO network-id 3\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"45IO\"}', '2026-04-18 20:43:47'),
(18, 3, 1, 'candidate', '{\"candidate\":\"candidate:2926025492 1 tcp 1518149375 192.168.1.223 9 typ host tcptype active generation 0 ufrag 45IO network-id 2 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"45IO\"}', '2026-04-18 20:43:47'),
(19, 3, 1, 'candidate', '{\"candidate\":\"candidate:206090190 1 tcp 1518280447 192.168.127.1 9 typ host tcptype active generation 0 ufrag 45IO network-id 1\",\"sdpMid\":\"1\",\"sdpMLineIndex\":1,\"usernameFragment\":\"45IO\"}', '2026-04-18 20:43:47'),
(20, 3, 1, 'candidate', '{\"candidate\":\"candidate:2675670833 1 tcp 1518214911 192.168.204.1 9 typ host tcptype active generation 0 ufrag 45IO network-id 3\",\"sdpMid\":\"1\",\"sdpMLineIndex\":1,\"usernameFragment\":\"45IO\"}', '2026-04-18 20:43:47'),
(21, 3, 1, 'candidate', '{\"candidate\":\"candidate:2926025492 1 tcp 1518149375 192.168.1.223 9 typ host tcptype active generation 0 ufrag 45IO network-id 2 network-cost 10\",\"sdpMid\":\"1\",\"sdpMLineIndex\":1,\"usernameFragment\":\"45IO\"}', '2026-04-18 20:43:47'),
(22, 3, 15, 'answer', '{\"sdp\":\"v=0\\r\\no=- 3891185702352136533 2 IN IP4 127.0.0.1\\r\\ns=-\\r\\nt=0 0\\r\\na=group:BUNDLE 0 1\\r\\na=extmap-allow-mixed\\r\\na=msid-semantic: WMS 89a59724-5b44-4785-be5b-23f1941de3e5\\r\\nm=audio 9 UDP\\/TLS\\/RTP\\/SAVPF 111 63 9 0 8 13 110 126\\r\\nc=IN IP4 0.0.0.0\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=ice-ufrag:RWbj\\r\\na=ice-pwd:Ghm3fibKvnJ1fzXnWRmCUiHy\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 8C:7B:8A:23:31:27:CF:59:27:CB:79:C7:44:5C:C5:13:7D:87:0A:7E:94:D4:35:C7:BF:F8:3D:65:3B:EB:5C:E0\\r\\na=setup:active\\r\\na=mid:0\\r\\na=extmap:1 urn:ietf:params:rtp-hdrext:ssrc-audio-level\\r\\na=extmap:2 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/abs-send-time\\r\\na=extmap:3 http:\\/\\/www.ietf.org\\/id\\/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=sendrecv\\r\\na=msid:89a59724-5b44-4785-be5b-23f1941de3e5 e6a2c575-ec76-428a-912e-c7abe2920dc1\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:111 opus\\/48000\\/2\\r\\na=rtcp-fb:111 transport-cc\\r\\na=fmtp:111 minptime=10;useinbandfec=1\\r\\na=rtpmap:63 red\\/48000\\/2\\r\\na=fmtp:63 111\\/111\\r\\na=rtpmap:9 G722\\/8000\\r\\na=rtpmap:0 PCMU\\/8000\\r\\na=rtpmap:8 PCMA\\/8000\\r\\na=rtpmap:13 CN\\/8000\\r\\na=rtpmap:110 telephone-event\\/48000\\r\\na=rtpmap:126 telephone-event\\/8000\\r\\na=ssrc:4040084727 cname:DEb2uLOclwHfs59s\\r\\nm=video 9 UDP\\/TLS\\/RTP\\/SAVPF 96 97 103 104 107 108 109 114 115 116 117 118 39 40 45 46 98 99 100 101 119 120 49 50 51 52 123 124 125\\r\\nc=IN IP4 0.0.0.0\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=ice-ufrag:RWbj\\r\\na=ice-pwd:Ghm3fibKvnJ1fzXnWRmCUiHy\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 8C:7B:8A:23:31:27:CF:59:27:CB:79:C7:44:5C:C5:13:7D:87:0A:7E:94:D4:35:C7:BF:F8:3D:65:3B:EB:5C:E0\\r\\na=setup:active\\r\\na=mid:1\\r\\na=extmap:14 urn:ietf:params:rtp-hdrext:toffset\\r\\na=extmap:2 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/abs-send-time\\r\\na=extmap:13 urn:3gpp:video-orientation\\r\\na=extmap:3 http:\\/\\/www.ietf.org\\/id\\/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:5 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/playout-delay\\r\\na=extmap:6 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/video-content-type\\r\\na=extmap:7 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/video-timing\\r\\na=extmap:8 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/color-space\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=extmap:10 urn:ietf:params:rtp-hdrext:sdes:rtp-stream-id\\r\\na=extmap:11 urn:ietf:params:rtp-hdrext:sdes:repaired-rtp-stream-id\\r\\na=sendrecv\\r\\na=msid:89a59724-5b44-4785-be5b-23f1941de3e5 16aa51a8-8247-4622-858c-a2ae7ca0d756\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:96 VP8\\/90000\\r\\na=rtcp-fb:96 goog-remb\\r\\na=rtcp-fb:96 transport-cc\\r\\na=rtcp-fb:96 ccm fir\\r\\na=rtcp-fb:96 nack\\r\\na=rtcp-fb:96 nack pli\\r\\na=rtpmap:97 rtx\\/90000\\r\\na=fmtp:97 apt=96\\r\\na=rtpmap:103 H264\\/90000\\r\\na=rtcp-fb:103 goog-remb\\r\\na=rtcp-fb:103 transport-cc\\r\\na=rtcp-fb:103 ccm fir\\r\\na=rtcp-fb:103 nack\\r\\na=rtcp-fb:103 nack pli\\r\\na=fmtp:103 level-asymmetry-allowed=1;packetization-mode=1;profile-level-id=42001f\\r\\na=rtpmap:104 rtx\\/90000\\r\\na=fmtp:104 apt=103\\r\\na=rtpmap:107 H264\\/90000\\r\\na=rtcp-fb:107 goog-remb\\r\\na=rtcp-fb:107 transport-cc\\r\\na=rtcp-fb:107 ccm fir\\r\\na=rtcp-fb:107 nack\\r\\na=rtcp-fb:107 nack pli\\r\\na=fmtp:107 level-asymmetry-allowed=1;packetization-mode=0;profile-level-id=42001f\\r\\na=rtpmap:108 rtx\\/90000\\r\\na=fmtp:108 apt=107\\r\\na=rtpmap:109 H264\\/90000\\r\\na=rtcp-fb:109 goog-remb\\r\\na=rtcp-fb:109 transport-cc\\r\\na=rtcp-fb:109 ccm fir\\r\\na=rtcp-fb:109 nack\\r\\na=rtcp-fb:109 nack pli\\r\\na=fmtp:109 level-asymmetry-allowed=1;packetization-mode=1;profile-level-id=42e01f\\r\\na=rtpmap:114 rtx\\/90000\\r\\na=fmtp:114 apt=109\\r\\na=rtpmap:115 H264\\/90000\\r\\na=rtcp-fb:115 goog-remb\\r\\na=rtcp-fb:115 transport-cc\\r\\na=rtcp-fb:115 ccm fir\\r\\na=rtcp-fb:115 nack\\r\\na=rtcp-fb:115 nack pli\\r\\na=fmtp:115 level-asymmetry-allowed=1;packetization-mode=0;profile-level-id=42e01f\\r\\na=rtpmap:116 rtx\\/90000\\r\\na=fmtp:116 apt=115\\r\\na=rtpmap:117 H264\\/90000\\r\\na=rtcp-fb:117 goog-remb\\r\\na=rtcp-fb:117 transport-cc\\r\\na=rtcp-fb:117 ccm fir\\r\\na=rtcp-fb:117 nack\\r\\na=rtcp-fb:117 nack pli\\r\\na=fmtp:117 level-asymmetry-allowed=1;packetization-mode=1;profile-level-id=4d001f\\r\\na=rtpmap:118 rtx\\/90000\\r\\na=fmtp:118 apt=117\\r\\na=rtpmap:39 H264\\/90000\\r\\na=rtcp-fb:39 goog-remb\\r\\na=rtcp-fb:39 transport-cc\\r\\na=rtcp-fb:39 ccm fir\\r\\na=rtcp-fb:39 nack\\r\\na=rtcp-fb:39 nack pli\\r\\na=fmtp:39 level-asymmetry-allowed=1;packetization-mode=0;profile-level-id=4d001f\\r\\na=rtpmap:40 rtx\\/90000\\r\\na=fmtp:40 apt=39\\r\\na=rtpmap:45 AV1\\/90000\\r\\na=rtcp-fb:45 goog-remb\\r\\na=rtcp-fb:45 transport-cc\\r\\na=rtcp-fb:45 ccm fir\\r\\na=rtcp-fb:45 nack\\r\\na=rtcp-fb:45 nack pli\\r\\na=fmtp:45 level-idx=5;profile=0;tier=0\\r\\na=rtpmap:46 rtx\\/90000\\r\\na=fmtp:46 apt=45\\r\\na=rtpmap:98 VP9\\/90000\\r\\na=rtcp-fb:98 goog-remb\\r\\na=rtcp-fb:98 transport-cc\\r\\na=rtcp-fb:98 ccm fir\\r\\na=rtcp-fb:98 nack\\r\\na=rtcp-fb:98 nack pli\\r\\na=fmtp:98 profile-id=0\\r\\na=rtpmap:99 rtx\\/90000\\r\\na=fmtp:99 apt=98\\r\\na=rtpmap:100 VP9\\/90000\\r\\na=rtcp-fb:100 goog-remb\\r\\na=rtcp-fb:100 transport-cc\\r\\na=rtcp-fb:100 ccm fir\\r\\na=rtcp-fb:100 nack\\r\\na=rtcp-fb:100 nack pli\\r\\na=fmtp:100 profile-id=2\\r\\na=rtpmap:101 rtx\\/90000\\r\\na=fmtp:101 apt=100\\r\\na=rtpmap:119 H264\\/90000\\r\\na=rtcp-fb:119 goog-remb\\r\\na=rtcp-fb:119 transport-cc\\r\\na=rtcp-fb:119 ccm fir\\r\\na=rtcp-fb:119 nack\\r\\na=rtcp-fb:119 nack pli\\r\\na=fmtp:119 level-asymmetry-allowed=1;packetization-mode=1;profile-level-id=64001f\\r\\na=rtpmap:120 rtx\\/90000\\r\\na=fmtp:120 apt=119\\r\\na=rtpmap:49 H265\\/90000\\r\\na=rtcp-fb:49 goog-remb\\r\\na=rtcp-fb:49 transport-cc\\r\\na=rtcp-fb:49 ccm fir\\r\\na=rtcp-fb:49 nack\\r\\na=rtcp-fb:49 nack pli\\r\\na=fmtp:49 level-id=180;profile-id=1;tier-flag=0;tx-mode=SRST\\r\\na=rtpmap:50 rtx\\/90000\\r\\na=fmtp:50 apt=49\\r\\na=rtpmap:51 H265\\/90000\\r\\na=rtcp-fb:51 goog-remb\\r\\na=rtcp-fb:51 transport-cc\\r\\na=rtcp-fb:51 ccm fir\\r\\na=rtcp-fb:51 nack\\r\\na=rtcp-fb:51 nack pli\\r\\na=fmtp:51 level-id=180;profile-id=2;tier-flag=0;tx-mode=SRST\\r\\na=rtpmap:52 rtx\\/90000\\r\\na=fmtp:52 apt=51\\r\\na=rtpmap:123 red\\/90000\\r\\na=rtpmap:124 rtx\\/90000\\r\\na=fmtp:124 apt=123\\r\\na=rtpmap:125 ulpfec\\/90000\\r\\na=ssrc-group:FID 1038189760 1119374198\\r\\na=ssrc:1038189760 cname:DEb2uLOclwHfs59s\\r\\na=ssrc:1119374198 cname:DEb2uLOclwHfs59s\\r\\n\",\"type\":\"answer\"}', '2026-04-18 20:43:56'),
(23, 3, 15, 'candidate', '{\"candidate\":\"candidate:2704275205 1 udp 2122194687 192.168.204.1 56832 typ host generation 0 ufrag RWbj network-id 3\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"RWbj\"}', '2026-04-18 20:43:56'),
(24, 3, 15, 'candidate', '{\"candidate\":\"candidate:840706042 1 udp 2122260223 192.168.127.1 56831 typ host generation 0 ufrag RWbj network-id 1\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"RWbj\"}', '2026-04-18 20:43:56'),
(25, 3, 15, 'candidate', '{\"candidate\":\"candidate:2419266336 1 udp 2122129151 192.168.1.223 56833 typ host generation 0 ufrag RWbj network-id 2 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"RWbj\"}', '2026-04-18 20:43:56'),
(26, 3, 15, 'candidate', '{\"candidate\":\"candidate:1288947042 1 tcp 1518280447 192.168.127.1 9 typ host tcptype active generation 0 ufrag RWbj network-id 1\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"RWbj\"}', '2026-04-18 20:43:56'),
(27, 3, 15, 'candidate', '{\"candidate\":\"candidate:3756004765 1 tcp 1518214911 192.168.204.1 9 typ host tcptype active generation 0 ufrag RWbj network-id 3\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"RWbj\"}', '2026-04-18 20:43:56'),
(28, 3, 15, 'candidate', '{\"candidate\":\"candidate:4009554360 1 tcp 1518149375 192.168.1.223 9 typ host tcptype active generation 0 ufrag RWbj network-id 2 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"RWbj\"}', '2026-04-18 20:43:56'),
(29, 3, 15, 'candidate', '{\"candidate\":\"candidate:1064956446 1 udp 1685921535 197.25.18.10 1034 typ srflx raddr 192.168.1.223 rport 56833 generation 0 ufrag RWbj network-id 2 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"RWbj\"}', '2026-04-18 20:43:56'),
(30, 3, 1, 'bye', '{\"reason\":\"Ended by participant\"}', '2026-04-18 20:44:31'),
(31, 4, 1, 'offer', '{\"sdp\":\"v=0\\r\\no=- 7975349443132756397 2 IN IP4 127.0.0.1\\r\\ns=-\\r\\nt=0 0\\r\\na=group:BUNDLE 0 1\\r\\na=extmap-allow-mixed\\r\\na=msid-semantic: WMS 75eec47c-e849-4199-941c-85957c436fe9\\r\\nm=audio 9 UDP\\/TLS\\/RTP\\/SAVPF 111 63 9 0 8 13 110 126\\r\\nc=IN IP4 0.0.0.0\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=ice-ufrag:DaBx\\r\\na=ice-pwd:Wtd92h5ZAQ3+d99TaAVyR9VM\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 7F:67:A5:88:20:1A:1E:7E:C5:00:A2:53:EA:0A:DE:F2:75:39:E7:82:62:E6:E5:EB:4B:8E:87:C2:8A:02:DF:4A\\r\\na=setup:actpass\\r\\na=mid:0\\r\\na=extmap:1 urn:ietf:params:rtp-hdrext:ssrc-audio-level\\r\\na=extmap:2 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/abs-send-time\\r\\na=extmap:3 http:\\/\\/www.ietf.org\\/id\\/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=sendrecv\\r\\na=msid:75eec47c-e849-4199-941c-85957c436fe9 c9b42388-bd8d-4cc1-8e5a-38a09e263bfa\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:111 opus\\/48000\\/2\\r\\na=rtcp-fb:111 transport-cc\\r\\na=fmtp:111 minptime=10;useinbandfec=1\\r\\na=rtpmap:63 red\\/48000\\/2\\r\\na=fmtp:63 111\\/111\\r\\na=rtpmap:9 G722\\/8000\\r\\na=rtpmap:0 PCMU\\/8000\\r\\na=rtpmap:8 PCMA\\/8000\\r\\na=rtpmap:13 CN\\/8000\\r\\na=rtpmap:110 telephone-event\\/48000\\r\\na=rtpmap:126 telephone-event\\/8000\\r\\na=ssrc:1617540748 cname:EGE8fEyamwht16\\/p\\r\\na=ssrc:1617540748 msid:75eec47c-e849-4199-941c-85957c436fe9 c9b42388-bd8d-4cc1-8e5a-38a09e263bfa\\r\\nm=video 9 UDP\\/TLS\\/RTP\\/SAVPF 96 97 103 104 107 108 109 114 115 116 117 118 39 40 45 46 98 99 100 101 119 120 49 50 51 52 123 124 125\\r\\nc=IN IP4 0.0.0.0\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=ice-ufrag:DaBx\\r\\na=ice-pwd:Wtd92h5ZAQ3+d99TaAVyR9VM\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 7F:67:A5:88:20:1A:1E:7E:C5:00:A2:53:EA:0A:DE:F2:75:39:E7:82:62:E6:E5:EB:4B:8E:87:C2:8A:02:DF:4A\\r\\na=setup:actpass\\r\\na=mid:1\\r\\na=extmap:14 urn:ietf:params:rtp-hdrext:toffset\\r\\na=extmap:2 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/abs-send-time\\r\\na=extmap:13 urn:3gpp:video-orientation\\r\\na=extmap:3 http:\\/\\/www.ietf.org\\/id\\/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:5 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/playout-delay\\r\\na=extmap:6 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/video-content-type\\r\\na=extmap:7 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/video-timing\\r\\na=extmap:8 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/color-space\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=extmap:10 urn:ietf:params:rtp-hdrext:sdes:rtp-stream-id\\r\\na=extmap:11 urn:ietf:params:rtp-hdrext:sdes:repaired-rtp-stream-id\\r\\na=sendrecv\\r\\na=msid:75eec47c-e849-4199-941c-85957c436fe9 5032332f-8f94-47ce-a724-1be6fb1864b3\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:96 VP8\\/90000\\r\\na=rtcp-fb:96 goog-remb\\r\\na=rtcp-fb:96 transport-cc\\r\\na=rtcp-fb:96 ccm fir\\r\\na=rtcp-fb:96 nack\\r\\na=rtcp-fb:96 nack pli\\r\\na=rtpmap:97 rtx\\/90000\\r\\na=fmtp:97 apt=96\\r\\na=rtpmap:103 H264\\/90000\\r\\na=rtcp-fb:103 goog-remb\\r\\na=rtcp-fb:103 transport-cc\\r\\na=rtcp-fb:103 ccm fir\\r\\na=rtcp-fb:103 nack\\r\\na=rtcp-fb:103 nack pli\\r\\na=fmtp:103 level-asymmetry-allowed=1;packetization-mode=1;profile-level-id=42001f\\r\\na=rtpmap:104 rtx\\/90000\\r\\na=fmtp:104 apt=103\\r\\na=rtpmap:107 H264\\/90000\\r\\na=rtcp-fb:107 goog-remb\\r\\na=rtcp-fb:107 transport-cc\\r\\na=rtcp-fb:107 ccm fir\\r\\na=rtcp-fb:107 nack\\r\\na=rtcp-fb:107 nack pli\\r\\na=fmtp:107 level-asymmetry-allowed=1;packetization-mode=0;profile-level-id=42001f\\r\\na=rtpmap:108 rtx\\/90000\\r\\na=fmtp:108 apt=107\\r\\na=rtpmap:109 H264\\/90000\\r\\na=rtcp-fb:109 goog-remb\\r\\na=rtcp-fb:109 transport-cc\\r\\na=rtcp-fb:109 ccm fir\\r\\na=rtcp-fb:109 nack\\r\\na=rtcp-fb:109 nack pli\\r\\na=fmtp:109 level-asymmetry-allowed=1;packetization-mode=1;profile-level-id=42e01f\\r\\na=rtpmap:114 rtx\\/90000\\r\\na=fmtp:114 apt=109\\r\\na=rtpmap:115 H264\\/90000\\r\\na=rtcp-fb:115 goog-remb\\r\\na=rtcp-fb:115 transport-cc\\r\\na=rtcp-fb:115 ccm fir\\r\\na=rtcp-fb:115 nack\\r\\na=rtcp-fb:115 nack pli\\r\\na=fmtp:115 level-asymmetry-allowed=1;packetization-mode=0;profile-level-id=42e01f\\r\\na=rtpmap:116 rtx\\/90000\\r\\na=fmtp:116 apt=115\\r\\na=rtpmap:117 H264\\/90000\\r\\na=rtcp-fb:117 goog-remb\\r\\na=rtcp-fb:117 transport-cc\\r\\na=rtcp-fb:117 ccm fir\\r\\na=rtcp-fb:117 nack\\r\\na=rtcp-fb:117 nack pli\\r\\na=fmtp:117 level-asymmetry-allowed=1;packetization-mode=1;profile-level-id=4d001f\\r\\na=rtpmap:118 rtx\\/90000\\r\\na=fmtp:118 apt=117\\r\\na=rtpmap:39 H264\\/90000\\r\\na=rtcp-fb:39 goog-remb\\r\\na=rtcp-fb:39 transport-cc\\r\\na=rtcp-fb:39 ccm fir\\r\\na=rtcp-fb:39 nack\\r\\na=rtcp-fb:39 nack pli\\r\\na=fmtp:39 level-asymmetry-allowed=1;packetization-mode=0;profile-level-id=4d001f\\r\\na=rtpmap:40 rtx\\/90000\\r\\na=fmtp:40 apt=39\\r\\na=rtpmap:45 AV1\\/90000\\r\\na=rtcp-fb:45 goog-remb\\r\\na=rtcp-fb:45 transport-cc\\r\\na=rtcp-fb:45 ccm fir\\r\\na=rtcp-fb:45 nack\\r\\na=rtcp-fb:45 nack pli\\r\\na=fmtp:45 level-idx=5;profile=0;tier=0\\r\\na=rtpmap:46 rtx\\/90000\\r\\na=fmtp:46 apt=45\\r\\na=rtpmap:98 VP9\\/90000\\r\\na=rtcp-fb:98 goog-remb\\r\\na=rtcp-fb:98 transport-cc\\r\\na=rtcp-fb:98 ccm fir\\r\\na=rtcp-fb:98 nack\\r\\na=rtcp-fb:98 nack pli\\r\\na=fmtp:98 profile-id=0\\r\\na=rtpmap:99 rtx\\/90000\\r\\na=fmtp:99 apt=98\\r\\na=rtpmap:100 VP9\\/90000\\r\\na=rtcp-fb:100 goog-remb\\r\\na=rtcp-fb:100 transport-cc\\r\\na=rtcp-fb:100 ccm fir\\r\\na=rtcp-fb:100 nack\\r\\na=rtcp-fb:100 nack pli\\r\\na=fmtp:100 profile-id=2\\r\\na=rtpmap:101 rtx\\/90000\\r\\na=fmtp:101 apt=100\\r\\na=rtpmap:119 H264\\/90000\\r\\na=rtcp-fb:119 goog-remb\\r\\na=rtcp-fb:119 transport-cc\\r\\na=rtcp-fb:119 ccm fir\\r\\na=rtcp-fb:119 nack\\r\\na=rtcp-fb:119 nack pli\\r\\na=fmtp:119 level-asymmetry-allowed=1;packetization-mode=1;profile-level-id=64001f\\r\\na=rtpmap:120 rtx\\/90000\\r\\na=fmtp:120 apt=119\\r\\na=rtpmap:49 H265\\/90000\\r\\na=rtcp-fb:49 goog-remb\\r\\na=rtcp-fb:49 transport-cc\\r\\na=rtcp-fb:49 ccm fir\\r\\na=rtcp-fb:49 nack\\r\\na=rtcp-fb:49 nack pli\\r\\na=fmtp:49 level-id=180;profile-id=1;tier-flag=0;tx-mode=SRST\\r\\na=rtpmap:50 rtx\\/90000\\r\\na=fmtp:50 apt=49\\r\\na=rtpmap:51 H265\\/90000\\r\\na=rtcp-fb:51 goog-remb\\r\\na=rtcp-fb:51 transport-cc\\r\\na=rtcp-fb:51 ccm fir\\r\\na=rtcp-fb:51 nack\\r\\na=rtcp-fb:51 nack pli\\r\\na=fmtp:51 level-id=180;profile-id=2;tier-flag=0;tx-mode=SRST\\r\\na=rtpmap:52 rtx\\/90000\\r\\na=fmtp:52 apt=51\\r\\na=rtpmap:123 red\\/90000\\r\\na=rtpmap:124 rtx\\/90000\\r\\na=fmtp:124 apt=123\\r\\na=rtpmap:125 ulpfec\\/90000\\r\\na=ssrc-group:FID 2166912058 2294214561\\r\\na=ssrc:2166912058 cname:EGE8fEyamwht16\\/p\\r\\na=ssrc:2166912058 msid:75eec47c-e849-4199-941c-85957c436fe9 5032332f-8f94-47ce-a724-1be6fb1864b3\\r\\na=ssrc:2294214561 cname:EGE8fEyamwht16\\/p\\r\\na=ssrc:2294214561 msid:75eec47c-e849-4199-941c-85957c436fe9 5032332f-8f94-47ce-a724-1be6fb1864b3\\r\\n\",\"type\":\"offer\"}', '2026-04-19 17:02:44'),
(32, 4, 1, 'candidate', '{\"candidate\":\"candidate:1257813734 1 tcp 1518280447 192.168.127.1 9 typ host tcptype active generation 0 ufrag DaBx network-id 1\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"DaBx\"}', '2026-04-19 17:02:44'),
(33, 4, 1, 'candidate', '{\"candidate\":\"candidate:1734961851 1 tcp 1518214911 192.168.204.1 9 typ host tcptype active generation 0 ufrag DaBx network-id 3\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"DaBx\"}', '2026-04-19 17:02:44'),
(34, 4, 1, 'candidate', '{\"candidate\":\"candidate:744268312 1 tcp 1518149375 192.168.1.223 9 typ host tcptype active generation 0 ufrag DaBx network-id 2 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"DaBx\"}', '2026-04-19 17:02:44'),
(35, 4, 1, 'candidate', '{\"candidate\":\"candidate:1257813734 1 tcp 1518280447 192.168.127.1 9 typ host tcptype active generation 0 ufrag DaBx network-id 1\",\"sdpMid\":\"1\",\"sdpMLineIndex\":1,\"usernameFragment\":\"DaBx\"}', '2026-04-19 17:02:44'),
(36, 4, 1, 'candidate', '{\"candidate\":\"candidate:1734961851 1 tcp 1518214911 192.168.204.1 9 typ host tcptype active generation 0 ufrag DaBx network-id 3\",\"sdpMid\":\"1\",\"sdpMLineIndex\":1,\"usernameFragment\":\"DaBx\"}', '2026-04-19 17:02:44'),
(37, 4, 1, 'candidate', '{\"candidate\":\"candidate:744268312 1 tcp 1518149375 192.168.1.223 9 typ host tcptype active generation 0 ufrag DaBx network-id 2 network-cost 10\",\"sdpMid\":\"1\",\"sdpMLineIndex\":1,\"usernameFragment\":\"DaBx\"}', '2026-04-19 17:02:44'),
(38, 4, 1, 'candidate', '{\"candidate\":\"candidate:322445766 1 udp 1685921535 197.27.63.62 32601 typ srflx raddr 192.168.1.223 rport 49159 generation 0 ufrag DaBx network-id 2 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"DaBx\"}', '2026-04-19 17:02:44'),
(39, 4, 1, 'candidate', '{\"candidate\":\"candidate:322445766 1 udp 1685921535 197.27.63.62 32602 typ srflx raddr 192.168.1.223 rport 49162 generation 0 ufrag DaBx network-id 2 network-cost 10\",\"sdpMid\":\"1\",\"sdpMLineIndex\":1,\"usernameFragment\":\"DaBx\"}', '2026-04-19 17:02:44'),
(40, 4, 1, 'bye', '{\"reason\":\"Ended by participant\"}', '2026-04-19 17:02:49'),
(41, 5, 1, 'offer', '{\"sdp\":\"v=0\\r\\no=- 6580660039676827283 2 IN IP4 127.0.0.1\\r\\ns=-\\r\\nt=0 0\\r\\na=group:BUNDLE 0\\r\\na=extmap-allow-mixed\\r\\na=msid-semantic: WMS 35b5f753-72b8-491f-9f15-1db9c16e8dc8\\r\\nm=audio 9 UDP\\/TLS\\/RTP\\/SAVPF 111 63 9 0 8 13 110 126\\r\\nc=IN IP4 0.0.0.0\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=ice-ufrag:pqZA\\r\\na=ice-pwd:bGNuyNjRrHXnj2QWBb8Xm5He\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 4C:71:FD:C9:37:91:6F:7D:F2:61:47:4D:41:A9:FB:A8:5A:C5:D8:E0:08:38:B4:81:7A:BE:56:BD:F0:FA:F9:3F\\r\\na=setup:actpass\\r\\na=mid:0\\r\\na=extmap:1 urn:ietf:params:rtp-hdrext:ssrc-audio-level\\r\\na=extmap:2 http:\\/\\/www.webrtc.org\\/experiments\\/rtp-hdrext\\/abs-send-time\\r\\na=extmap:3 http:\\/\\/www.ietf.org\\/id\\/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=sendrecv\\r\\na=msid:35b5f753-72b8-491f-9f15-1db9c16e8dc8 100ba4ee-50a2-40aa-8fdc-864f2fe17ea4\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:111 opus\\/48000\\/2\\r\\na=rtcp-fb:111 transport-cc\\r\\na=fmtp:111 minptime=10;useinbandfec=1\\r\\na=rtpmap:63 red\\/48000\\/2\\r\\na=fmtp:63 111\\/111\\r\\na=rtpmap:9 G722\\/8000\\r\\na=rtpmap:0 PCMU\\/8000\\r\\na=rtpmap:8 PCMA\\/8000\\r\\na=rtpmap:13 CN\\/8000\\r\\na=rtpmap:110 telephone-event\\/48000\\r\\na=rtpmap:126 telephone-event\\/8000\\r\\na=ssrc:2135641239 cname:4Ggz\\/mv8kOVbLs4q\\r\\na=ssrc:2135641239 msid:35b5f753-72b8-491f-9f15-1db9c16e8dc8 100ba4ee-50a2-40aa-8fdc-864f2fe17ea4\\r\\n\",\"type\":\"offer\"}', '2026-04-19 17:04:58'),
(42, 5, 1, 'candidate', '{\"candidate\":\"candidate:1191131495 1 udp 1685921535 197.27.63.62 32601 typ srflx raddr 192.168.1.223 rport 64651 generation 0 ufrag pqZA network-id 2 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"pqZA\"}', '2026-04-19 17:04:59'),
(43, 5, 1, 'candidate', '{\"candidate\":\"candidate:894766619 1 tcp 1518280447 192.168.127.1 9 typ host tcptype active generation 0 ufrag pqZA network-id 1\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"pqZA\"}', '2026-04-19 17:04:59'),
(44, 5, 1, 'candidate', '{\"candidate\":\"candidate:2791759588 1 tcp 1518214911 192.168.204.1 9 typ host tcptype active generation 0 ufrag pqZA network-id 3\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"pqZA\"}', '2026-04-19 17:04:59'),
(45, 5, 1, 'candidate', '{\"candidate\":\"candidate:2541370049 1 tcp 1518149375 192.168.1.223 9 typ host tcptype active generation 0 ufrag pqZA network-id 2 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"pqZA\"}', '2026-04-19 17:04:59'),
(46, 5, 1, 'bye', '{\"reason\":\"Ended by participant\"}', '2026-04-19 17:05:33');

-- --------------------------------------------------------

--
-- Table structure for table `contracts`
--

CREATE TABLE `contracts` (
  `id` int(11) NOT NULL,
  `job_offer_id` int(11) NOT NULL,
  `freelancer_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `terms` text NOT NULL,
  `status` enum('draft','active','completed','canceled') NOT NULL DEFAULT 'draft',
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `signed_at` datetime DEFAULT NULL,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `created_by_client_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `id` int(11) NOT NULL,
  `type` enum('private','group') NOT NULL DEFAULT 'private',
  `name` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `conversation_members`
--

CREATE TABLE `conversation_members` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('member','admin') NOT NULL DEFAULT 'member',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `friends`
--

CREATE TABLE `friends` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `friend_id` int(11) DEFAULT NULL,
  `user_one_id` int(11) DEFAULT NULL,
  `user_two_id` int(11) DEFAULT NULL,
  `source_request_id` int(11) DEFAULT NULL,
  `status` enum('pending','accepted','blocked') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `friends`
--

INSERT INTO `friends` (`id`, `user_id`, `friend_id`, `user_one_id`, `user_two_id`, `source_request_id`, `status`, `created_at`) VALUES
(1, 1, 15, 1, 15, 1, 'accepted', '2026-04-18 14:40:54'),
(2, 15, 26, 15, 26, 2, 'accepted', '2026-04-18 19:12:05'),
(3, 3, 15, 3, 15, 14, 'accepted', '2026-04-18 21:02:33');

-- --------------------------------------------------------

--
-- Table structure for table `friend_requests`
--

CREATE TABLE `friend_requests` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `request_message` varchar(255) DEFAULT NULL,
  `status` enum('pending','accepted','declined','blocked','canceled') NOT NULL DEFAULT 'pending',
  `responded_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `friend_requests`
--

INSERT INTO `friend_requests` (`id`, `sender_id`, `receiver_id`, `request_message`, `status`, `responded_at`, `created_at`, `updated_at`) VALUES
(1, 1, 15, 'Let us connect on Diversity.is.', 'accepted', '2026-04-18 15:40:54', '2026-04-18 15:40:42', '2026-04-18 15:40:54'),
(2, 26, 15, 'Let us connect on Diversity.is.', 'accepted', '2026-04-18 20:12:05', '2026-04-18 20:11:55', '2026-04-18 20:12:05'),
(3, 1, 26, 'Let\'s connect on Diversity.is.', 'pending', NULL, '2026-04-18 21:27:48', '2026-04-18 21:27:48'),
(4, 15, 32, 'Let us connect on Diversity.is.', 'pending', NULL, '2026-04-18 21:58:40', '2026-04-18 21:58:40'),
(5, 15, 40, 'Let us connect on Diversity.is.', 'pending', NULL, '2026-04-18 21:58:41', '2026-04-18 21:58:41'),
(6, 15, 49, 'Let us connect on Diversity.is.', 'pending', NULL, '2026-04-18 21:58:41', '2026-04-18 21:58:41'),
(7, 15, 39, 'Let us connect on Diversity.is.', 'pending', NULL, '2026-04-18 21:58:42', '2026-04-18 21:58:42'),
(8, 15, 48, 'Let us connect on Diversity.is.', 'pending', NULL, '2026-04-18 21:58:42', '2026-04-18 21:58:42'),
(9, 15, 41, 'Let us connect on Diversity.is.', 'pending', NULL, '2026-04-18 21:58:44', '2026-04-18 21:58:44'),
(10, 15, 36, 'Let us connect on Diversity.is.', 'pending', NULL, '2026-04-18 21:58:45', '2026-04-18 21:58:45'),
(11, 15, 45, 'Let us connect on Diversity.is.', 'pending', NULL, '2026-04-18 21:58:45', '2026-04-18 21:58:45'),
(12, 15, 34, 'Let us connect on Diversity.is.', 'pending', NULL, '2026-04-18 21:58:46', '2026-04-18 21:58:46'),
(13, 15, 37, 'Let us connect on Diversity.is.', 'pending', NULL, '2026-04-18 21:58:47', '2026-04-18 21:58:47'),
(14, 3, 15, 'Let\'s connect on Diversity.is.', 'accepted', '2026-04-18 22:02:33', '2026-04-18 22:02:26', '2026-04-18 22:02:33');

--
-- Triggers `friend_requests`
--
DELIMITER $$
CREATE TRIGGER `trg_friend_requests_after_update` AFTER UPDATE ON `friend_requests` FOR EACH ROW BEGIN
  IF NEW.`status` = 'accepted' AND OLD.`status` <> 'accepted' THEN
    INSERT IGNORE INTO `friends` (`user_id`, `friend_id`, `user_one_id`, `user_two_id`, `source_request_id`, `status`, `created_at`)
    VALUES (
      LEAST(NEW.`sender_id`, NEW.`receiver_id`),
      GREATEST(NEW.`sender_id`, NEW.`receiver_id`),
      LEAST(NEW.`sender_id`, NEW.`receiver_id`),
      GREATEST(NEW.`sender_id`, NEW.`receiver_id`),
      NEW.`id`,
      'accepted',
      NOW()
    );

    INSERT IGNORE INTO `private_conversations` (`user_one_id`, `user_two_id`, `last_message_at`, `created_at`, `updated_at`)
    VALUES (
      LEAST(NEW.`sender_id`, NEW.`receiver_id`),
      GREATEST(NEW.`sender_id`, NEW.`receiver_id`),
      NULL,
      NOW(),
      NOW()
    );
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_friend_requests_before_insert` BEFORE INSERT ON `friend_requests` FOR EACH ROW BEGIN
  IF NEW.`sender_id` = NEW.`receiver_id` THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'You cannot send a friend request to yourself.';
  END IF;

  IF EXISTS (
    SELECT 1
    FROM `friends` f
    WHERE f.`user_one_id` = LEAST(NEW.`sender_id`, NEW.`receiver_id`)
      AND f.`user_two_id` = GREATEST(NEW.`sender_id`, NEW.`receiver_id`)
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Users are already connected as friends.';
  END IF;

  IF EXISTS (
    SELECT 1
    FROM `friend_requests` fr
    WHERE fr.`status` = 'pending'
      AND (
        (fr.`sender_id` = NEW.`sender_id` AND fr.`receiver_id` = NEW.`receiver_id`)
        OR
        (fr.`sender_id` = NEW.`receiver_id` AND fr.`receiver_id` = NEW.`sender_id`)
      )
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A pending friend request already exists for this user pair.';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_friend_requests_before_update` BEFORE UPDATE ON `friend_requests` FOR EACH ROW BEGIN
  IF NEW.`status` <> OLD.`status`
     AND NEW.`status` <> 'pending'
     AND NEW.`responded_at` IS NULL THEN
    SET NEW.`responded_at` = NOW();
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `groups`
--

CREATE TABLE `groups` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `is_private` tinyint(1) NOT NULL DEFAULT 0,
  `last_message_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `group_chats`
--

CREATE TABLE `group_chats` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `conversation_id` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `is_private` tinyint(1) NOT NULL DEFAULT 0,
  `last_message_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `group_chats`
--

INSERT INTO `group_chats` (`id`, `name`, `created_by`, `created_at`, `conversation_id`, `description`, `avatar_url`, `is_private`, `last_message_at`, `updated_at`) VALUES
(1, 's', 15, '2026-04-18 19:12:20', NULL, NULL, NULL, 0, NULL, '2026-04-18 20:12:20'),
(2, 'sgima', 15, '2026-04-18 21:03:13', NULL, NULL, NULL, 0, NULL, '2026-04-18 22:03:13');

-- --------------------------------------------------------

--
-- Table structure for table `group_chat_members`
--

CREATE TABLE `group_chat_members` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `group_chat_id` int(11) DEFAULT NULL,
  `role` enum('owner','admin','member') NOT NULL DEFAULT 'member',
  `left_at` datetime DEFAULT NULL,
  `is_muted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `group_members`
--

CREATE TABLE `group_members` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('owner','admin','member') NOT NULL DEFAULT 'member',
  `joined_at` datetime NOT NULL DEFAULT current_timestamp(),
  `left_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `group_reports`
--

CREATE TABLE `group_reports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `group_chat_id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `reported_user_id` int(11) DEFAULT NULL,
  `message_id` bigint(20) DEFAULT NULL,
  `reason` varchar(190) NOT NULL,
  `details` text DEFAULT NULL,
  `status` enum('pending','reviewed','resolved','dismissed') NOT NULL DEFAULT 'pending',
  `moderator_id` int(11) DEFAULT NULL,
  `moderation_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_offers`
--

CREATE TABLE `job_offers` (
  `id` int(11) NOT NULL,
  `title` varchar(180) NOT NULL,
  `description` text NOT NULL,
  `budget` decimal(12,2) NOT NULL DEFAULT 0.00,
  `skills_required` text DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `experience_level` varchar(40) DEFAULT NULL,
  `project_type` varchar(60) DEFAULT NULL,
  `status` enum('open','in_progress','closed','archived') NOT NULL DEFAULT 'open',
  `deadline_at` datetime DEFAULT NULL,
  `client_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_offers`
--

INSERT INTO `job_offers` (`id`, `title`, `description`, `budget`, `skills_required`, `location`, `experience_level`, `project_type`, `status`, `deadline_at`, `client_id`, `created_at`, `updated_at`) VALUES
(1, 'AMAMAMAMANJN', 'Hhttp://localhost:3000/Views/FrontOffice/home.phphttp://localhost:3000/Views/FrontOffice/home.php', 10.00, 'REACT', 'Ariana, Tunisie', 'Mid', 'Fixed Price', 'in_progress', '2026-04-16 09:30:00', 1, '2026-04-15 09:31:12', '2026-04-15 09:31:40'),
(2, 'AMAMAMAMANJN', 'Hhttp://localhost:3000/Views/FrontOffice/home.phphttp://localhost:3000/Views/FrontOffice/home.php', 10.00, 'REACT', 'Ariana, Tunisie', 'Mid', 'Fixed Price', 'open', '2026-04-16 09:30:00', 1, '2026-04-15 09:31:37', '2026-04-15 09:31:37');

-- --------------------------------------------------------

--
-- Table structure for table `job_offer_applications`
--

CREATE TABLE `job_offer_applications` (
  `id` int(11) NOT NULL,
  `job_offer_id` int(11) NOT NULL,
  `freelancer_id` int(11) NOT NULL,
  `cover_letter` text DEFAULT NULL,
  `status` enum('pending','accepted','rejected','withdrawn') NOT NULL DEFAULT 'pending',
  `applied_at` datetime NOT NULL DEFAULT current_timestamp(),
  `decided_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_offer_applications`
--

INSERT INTO `job_offer_applications` (`id`, `job_offer_id`, `freelancer_id`, `cover_letter`, `status`, `applied_at`, `decided_at`, `created_at`, `updated_at`) VALUES
(1, 1, 4, 'JJJJ', 'accepted', '2026-04-15 09:31:32', '2026-04-15 09:31:40', '2026-04-15 09:31:32', '2026-04-15 09:31:40');

-- --------------------------------------------------------

--
-- Table structure for table `linked_accounts`
--

CREATE TABLE `linked_accounts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `provider` varchar(50) NOT NULL,
  `provider_id` varchar(255) NOT NULL,
  `provider_username` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `platform` varchar(50) DEFAULT NULL,
  `account_label` varchar(80) DEFAULT NULL,
  `username` varchar(120) DEFAULT NULL,
  `profile_url` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `metadata` longtext DEFAULT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_checked_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `conversation_id` int(11) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `edited_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `message_type` enum('text','image','video','audio','file','system') NOT NULL DEFAULT 'text',
  `private_conversation_id` int(11) DEFAULT NULL,
  `group_chat_id` int(11) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `media_url` varchar(255) DEFAULT NULL,
  `is_edited` tinyint(1) NOT NULL DEFAULT 0,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `group_id`, `content`, `is_read`, `created_at`, `conversation_id`, `metadata`, `edited_at`, `deleted_at`, `message_type`, `private_conversation_id`, `group_chat_id`, `body`, `media_url`, `is_edited`, `is_deleted`, `updated_at`) VALUES
(1, 15, NULL, NULL, NULL, 0, '2026-04-18 14:41:01', NULL, NULL, NULL, NULL, 'text', 1, NULL, 'hi bro', NULL, 0, 0, '2026-04-18 15:41:01'),
(2, 1, NULL, NULL, NULL, 0, '2026-04-18 14:41:18', NULL, NULL, NULL, NULL, 'text', 1, NULL, 'nigga fuck you', NULL, 0, 0, '2026-04-18 15:41:18'),
(3, 1, NULL, NULL, NULL, 0, '2026-04-18 14:41:29', NULL, NULL, NULL, NULL, 'text', 1, NULL, '💪', NULL, 0, 0, '2026-04-18 15:41:29'),
(4, 1, NULL, NULL, NULL, 0, '2026-04-18 17:52:30', NULL, NULL, NULL, NULL, 'text', 1, NULL, '🎊', NULL, 0, 0, '2026-04-18 18:52:30'),
(5, 1, NULL, NULL, NULL, 0, '2026-04-18 17:52:32', NULL, NULL, NULL, NULL, 'text', 1, NULL, '🎊', NULL, 0, 0, '2026-04-18 18:52:32'),
(6, 1, NULL, NULL, NULL, 0, '2026-04-18 17:52:32', NULL, NULL, NULL, NULL, 'text', 1, NULL, '🎊', NULL, 0, 0, '2026-04-18 18:52:32'),
(7, 1, NULL, NULL, NULL, 0, '2026-04-18 17:52:32', NULL, NULL, NULL, NULL, 'text', 1, NULL, '🎊', NULL, 0, 0, '2026-04-18 18:52:32'),
(8, 1, NULL, NULL, NULL, 0, '2026-04-18 17:52:33', NULL, NULL, NULL, NULL, 'text', 1, NULL, '🎊', NULL, 0, 0, '2026-04-18 18:52:33'),
(9, 1, NULL, NULL, NULL, 0, '2026-04-18 17:52:33', NULL, NULL, NULL, NULL, 'text', 1, NULL, '🎊', NULL, 0, 0, '2026-04-18 18:52:33'),
(10, 1, NULL, NULL, NULL, 0, '2026-04-18 17:52:33', NULL, NULL, NULL, NULL, 'text', 1, NULL, '🎊', NULL, 0, 0, '2026-04-18 18:52:33'),
(11, 1, NULL, NULL, NULL, 0, '2026-04-18 17:52:33', NULL, NULL, NULL, NULL, 'text', 1, NULL, '🎊', NULL, 0, 0, '2026-04-18 18:52:33'),
(12, 1, NULL, NULL, NULL, 0, '2026-04-18 17:52:33', NULL, NULL, NULL, NULL, 'text', 1, NULL, '🎊', NULL, 0, 0, '2026-04-18 18:52:33'),
(13, 1, NULL, NULL, NULL, 0, '2026-04-18 17:52:34', NULL, NULL, NULL, NULL, 'text', 1, NULL, '🎊', NULL, 0, 0, '2026-04-18 18:52:34'),
(14, 1, NULL, NULL, NULL, 0, '2026-04-18 17:52:34', NULL, NULL, NULL, NULL, 'text', 1, NULL, '🎊', NULL, 0, 0, '2026-04-18 18:52:34'),
(15, 1, NULL, NULL, NULL, 0, '2026-04-18 17:52:34', NULL, NULL, NULL, NULL, 'text', 1, NULL, '🎊', NULL, 0, 0, '2026-04-18 18:52:34'),
(16, 1, NULL, NULL, NULL, 0, '2026-04-18 17:52:34', NULL, NULL, NULL, NULL, 'text', 1, NULL, '🎊', NULL, 0, 0, '2026-04-18 18:52:34'),
(17, 15, NULL, NULL, NULL, 0, '2026-04-18 18:10:22', NULL, NULL, NULL, NULL, 'text', 1, NULL, 'Yes, totally agree! ✨', NULL, 0, 0, '2026-04-18 19:10:22'),
(18, 15, NULL, NULL, NULL, 0, '2026-04-18 18:10:23', NULL, NULL, NULL, NULL, 'text', 1, NULL, 'Yes, totally agree! ✨', NULL, 0, 0, '2026-04-18 19:10:23'),
(19, 15, NULL, NULL, NULL, 0, '2026-04-18 18:10:26', NULL, NULL, NULL, NULL, 'text', 1, NULL, 'Message deleted', NULL, 0, 1, '2026-04-18 21:45:13'),
(20, 15, NULL, NULL, NULL, 0, '2026-04-18 19:12:20', NULL, NULL, NULL, NULL, 'system', NULL, 1, 'Group chat created.', NULL, 0, 0, '2026-04-18 20:12:20'),
(21, 15, NULL, NULL, NULL, 0, '2026-04-18 21:03:13', NULL, NULL, NULL, NULL, 'system', NULL, 2, 'Group chat created.', NULL, 0, 0, '2026-04-18 22:03:13'),
(22, 1, NULL, NULL, NULL, 0, '2026-04-19 17:15:43', NULL, NULL, NULL, NULL, 'text', 1, NULL, 'Message deleted', NULL, 0, 1, '2026-04-19 18:15:56');

-- --------------------------------------------------------

--
-- Table structure for table `message_attachments`
--

CREATE TABLE `message_attachments` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `file_url` varchar(1024) NOT NULL,
  `mime_type` varchar(128) DEFAULT NULL,
  `size` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `message_reactions`
--

CREATE TABLE `message_reactions` (
  `id` bigint(20) NOT NULL,
  `message_id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reaction` varchar(20) NOT NULL DEFAULT 'like',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `message_reads`
--

CREATE TABLE `message_reads` (
  `id` bigint(20) NOT NULL,
  `message_id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `read_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `message_reads`
--

INSERT INTO `message_reads` (`id`, `message_id`, `user_id`, `read_at`) VALUES
(1, 1, 1, '2026-04-18 15:41:07'),
(2, 2, 15, '2026-04-18 15:41:20'),
(3, 3, 15, '2026-04-18 15:41:36'),
(4, 4, 15, '2026-04-18 18:52:41'),
(5, 5, 15, '2026-04-18 18:52:41'),
(6, 6, 15, '2026-04-18 18:52:41'),
(7, 7, 15, '2026-04-18 18:52:41'),
(8, 8, 15, '2026-04-18 18:52:41'),
(9, 9, 15, '2026-04-18 18:52:41'),
(10, 10, 15, '2026-04-18 18:52:41'),
(11, 11, 15, '2026-04-18 18:52:41'),
(12, 12, 15, '2026-04-18 18:52:41'),
(13, 13, 15, '2026-04-18 18:52:41'),
(14, 14, 15, '2026-04-18 18:52:41'),
(15, 15, 15, '2026-04-18 18:52:41'),
(16, 16, 15, '2026-04-18 18:52:41'),
(19, 17, 1, '2026-04-18 19:10:26'),
(20, 18, 1, '2026-04-18 19:10:26'),
(21, 19, 1, '2026-04-18 19:10:26');

-- --------------------------------------------------------

--
-- Table structure for table `private_conversations`
--

CREATE TABLE `private_conversations` (
  `id` int(11) NOT NULL,
  `user_one_id` int(11) NOT NULL,
  `user_two_id` int(11) NOT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `private_conversations`
--

INSERT INTO `private_conversations` (`id`, `user_one_id`, `user_two_id`, `last_message_at`, `created_at`, `updated_at`) VALUES
(1, 1, 15, '2026-04-19 18:15:43', '2026-04-18 15:40:54', '2026-04-19 18:15:43'),
(2, 15, 26, NULL, '2026-04-18 20:12:05', '2026-04-18 20:12:05'),
(3, 3, 15, NULL, '2026-04-18 22:02:33', '2026-04-18 22:02:33');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `title` varchar(180) NOT NULL,
  `description` text NOT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `short_description` varchar(255) DEFAULT NULL,
  `technologies` text DEFAULT NULL,
  `status` enum('planning','active','completed','on_hold','archived') NOT NULL DEFAULT 'planning',
  `progress_percent` int(11) NOT NULL DEFAULT 0,
  `budget` decimal(12,2) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `owner_id` int(11) NOT NULL,
  `visibility` enum('private','team','public') NOT NULL DEFAULT 'team',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stories`
--

CREATE TABLE `stories` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `media_url` varchar(255) NOT NULL,
  `music_url` varchar(500) DEFAULT NULL,
  `music_title` varchar(255) DEFAULT NULL,
  `drawing_data` longtext DEFAULT NULL,
  `text_layers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`text_layers`)),
  `sticker_layers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sticker_layers`)),
  `filter_css` varchar(255) DEFAULT NULL,
  `gradient_bg` varchar(255) DEFAULT NULL,
  `duration` int(11) DEFAULT 5,
  `media_type` enum('image','video') DEFAULT 'image',
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `caption` varchar(1024) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `visibility` enum('public','friends','close_friends','private') NOT NULL DEFAULT 'friends',
  `story_type` enum('image','video','text') NOT NULL DEFAULT 'image',
  `location_label` varchar(255) DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `music_id` varchar(120) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stories`
--

INSERT INTO `stories` (`id`, `user_id`, `media_url`, `music_url`, `music_title`, `drawing_data`, `text_layers`, `sticker_layers`, `filter_css`, `gradient_bg`, `duration`, `media_type`, `expires_at`, `created_at`, `caption`, `updated_at`, `visibility`, `story_type`, `location_label`, `is_archived`, `music_id`) VALUES
(1, 1, 'assets/uploads/stories/story_1_1776542921.webm', NULL, NULL, 'data:image/png;base64,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', NULL, NULL, NULL, NULL, 5, 'image', '2026-04-19 20:08:42', '2026-04-18 20:08:42', NULL, NULL, 'public', 'video', NULL, 0, NULL),
(2, 26, 'assets/uploads/stories/story_26_1776542988.webm', NULL, NULL, 'data:image/png;base64,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', NULL, NULL, NULL, NULL, 5, 'image', '2026-04-19 20:09:48', '2026-04-18 20:09:48', NULL, NULL, 'public', 'video', NULL, 0, NULL),
(3, 1, 'assets/uploads/stories/story_1_1776618837.jpg', NULL, NULL, 'data:image/png;base64,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', '[{\"text\":\"free palpestine\",\"x\":\"16.825px\",\"y\":\"373.463px\",\"scale\":1,\"rotation\":0,\"z_index\":26,\"color\":\"rgb(239, 68, 68)\",\"fontSize\":\"28px\",\"fontFamily\":\"\\\"JetBrains Mono\\\", monospace\"}]', '[{\"emoji\":\"😂\",\"x\":\"11.2258%\",\"y\":\"53.4696%\",\"scale\":2.024198510003245,\"rotation\":-33.06311549935466,\"z_index\":22}]', 'saturate(.5) brightness(.92)', NULL, 30, 'image', '2026-04-20 17:13:57', '2026-04-19 17:13:57', 'free palpestine', NULL, 'public', 'image', NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `story_media`
--

CREATE TABLE `story_media` (
  `id` int(11) NOT NULL,
  `story_id` int(11) NOT NULL,
  `media_url` varchar(1024) NOT NULL,
  `media_type` enum('image','video') NOT NULL,
  `order_idx` int(10) UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `story_views`
--

CREATE TABLE `story_views` (
  `id` int(11) NOT NULL,
  `story_id` int(11) NOT NULL,
  `viewer_id` int(11) NOT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `story_views`
--

INSERT INTO `story_views` (`id`, `story_id`, `viewer_id`, `viewed_at`) VALUES
(1, 2, 1, '2026-04-18 20:28:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(120) NOT NULL,
  `last_name` varchar(120) NOT NULL,
  `email` varchar(180) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `role` varchar(50) DEFAULT 'user',
  `status` tinyint(1) DEFAULT 1,
  `avatar_url` text DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `xp` int(11) DEFAULT 0,
  `is_blocked` tinyint(1) DEFAULT 0,
  `last_seen` datetime DEFAULT NULL,
  `face_images_path` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `exact_location` varchar(255) DEFAULT NULL,
  `latitude` double DEFAULT NULL,
  `longitude` double DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `password`, `phone`, `role`, `status`, `avatar_url`, `country`, `bio`, `skills`, `xp`, `is_blocked`, `last_seen`, `face_images_path`, `created_at`, `updated_at`, `exact_location`, `latitude`, `longitude`) VALUES
(1, 'Admin', 'Root', 'admin@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+216 00 000 000', 'admin', 1, 'https://api.dicebear.com/9.x/avataaars/svg?seed=profile-header-random-Admin-Root-1776453500934', 'Tunisia', 'Platform administrator with full access.', 'PHP,MySQL,JavaScript', 500, 0, NULL, NULL, '2026-04-15 09:21:11', '2026-04-18 14:20:55', 'Tunis, Tunisia', 36.8065, 10.1815),
(3, 'Jesus', 'Christ', 'jj@jj.jj', '$2y$10$dSvfCiqYenY5t9jQDMYDiuC/fukD6.tmjg5dzOxZ4RxMFWGNvPIpy', '+21627441148', 'freelancer', 1, '../../assets/faces/face_69df4b901be1d2.01626466_weird-white-caucasian-facial-expression-260nw-43357417.webp', 'Tunisia', 'Proud member of Diversity.is, building meaningful collaborations every day. Member since April 15, 2026.', '', 0, 0, '2026-04-18 22:02:08', NULL, '2026-04-15 10:25:00', '2026-04-18 22:02:08', NULL, 36.8065, 10.1815),
(4, 'Aziz', 'Abidi', 'aziz.abidi@esprit.tn', '$2y$10$kYiwfJpjP8ZLrCAQn0S7RueEYIm1p0OFZcavFYXVOkXbQJL0g3aSe', '+9121459872', 'client', 1, 'https://api.dicebear.com/9.x/avataaars/svg?seed=profile-header-random-Aziz-BOUZIDI-1776244899507', 'India', 'Proud member of Diversity.is, building meaningful collaborations every day. Member since April 15, 2026.', '', 0, 0, NULL, NULL, '2026-04-15 10:29:27', '2026-04-18 14:21:28', NULL, 28.6139, 77.209),
(5, 'Incel', 'IS', 'aa@aa.aa', '$2y$10$3wjfSaZ2DqKwmLkflivsJ..PC8RU27tghJmAWYv/rWSwJuAnD0WgC', '+96721458798', 'client', 1, 'https://api.dicebear.com/9.x/adventurer/svg?seed=Incel-IS-aa%40aa.aa', 'Yemen', NULL, NULL, 0, 0, NULL, NULL, '2026-04-15 10:36:58', '2026-04-15 09:36:58', NULL, NULL, NULL),
(6, 'Afshar', 'Khan', 'aa@aa.cc', '$2y$10$7qh.1pUdR6h.Fwq8NM4MhuPJnwuWg5K1QQXfuF0exHMhzhVM.5Sfy', '+9814587458', 'client', 1, 'https://api.dicebear.com/9.x/avataaars/svg?seed=profile-header-random-Afshar-Khan-1776243157734', 'Iran', 'public const BIO_MAX_LENGTH = 1000;\n    public const BIO_MAX_LENGTH = 1000;\n    public const BIO_MAX_LENGTH = 1000;\n    public const BIO_MAX_LENGTH = 1000;', '', 0, 0, '2026-04-15 10:00:21', NULL, '2026-04-15 10:52:26', '2026-04-15 10:00:21', NULL, NULL, NULL),
(8, 'Augutus', 'Aurelian', 'oo@oo.oo', '$2y$10$FS1jq5X5Vs9yIvJKVvM7fOQgjSu4aRvk5iVaeL5dfB/0p2UeMVMHa', '+3914587458', 'freelancer', 1, '../../assets/faces/face_69df598e5e6f33.69682886_ab6761610000e5eb364090189a2ea76c99adadf6.jpg', 'Italy', 'Proud member of Diversity.is, building meaningful collaborations every day. Member since April 15, 2026.', '', 0, 0, NULL, NULL, '2026-04-15 11:23:23', '2026-04-18 14:21:28', NULL, 41.9028, 12.4964),
(9, 'AbdSellem', 'Ghobnani', 'abd@gmail.com', '$2y$10$3AHcoW7Jn3izNuOEsiwpfOAz8jUxXo1ugkwd8SowdocZBwArxd3xW', '+21693365245', 'freelancer', 1, '../../assets/faces/face_69df5e1d242bd8.51373674_4B9A8260.jpg', 'Tunisia', 'Proud member of Diversity.is, building meaningful collaborations every day. Member since April 15, 2026.', '', 0, 0, NULL, NULL, '2026-04-15 11:44:02', '2026-04-18 14:21:28', NULL, 36.8065, 10.1815),
(10, 'Mia', 'Khan', 'mia.khan10@example.com', '$2b$10$w6Bihm5Br4Rnen6KKrwOmezPtb63wQ1ao8WcCvRsEoi5YHRU5PPPi', '+44 20 7946 0123', 'user', 1, 'https://api.dicebear.com/6.x/bottts/svg?seed=MiaKhan10', 'United Kingdom', 'Product designer passionate about UX and inclusive tools.', 'Design,UX,Research', 120, 0, NULL, NULL, '2026-04-17 22:22:58', '2026-04-18 14:21:28', 'London, United Kingdom', 51.5072, -0.1276),
(11, 'Noah', 'Tanaka', 'noah.tanaka11@example.com', '$2b$10$w6Bihm5Br4Rnen6KKrwOmezPtb63wQ1ao8WcCvRsEoi5YHRU5PPPi', '+81 3 1234 5678', 'user', 1, 'https://api.dicebear.com/6.x/bottts/svg?seed=NoahTanaka11', 'Japan', 'Full-stack engineer who loves clean code and strong coffee.', 'JavaScript,PHP,React', 185, 0, NULL, NULL, '2026-04-17 22:22:58', '2026-04-18 14:21:28', 'Shibuya, Tokyo, Japan', 35.6762, 139.6503),
(12, 'Leila', 'Gomez', 'leila.gomez12@example.com', '$2b$10$w6Bihm5Br4Rnen6KKrwOmezPtb63wQ1ao8WcCvRsEoi5YHRU5PPPi', '+55 11 9876 5432', 'user', 1, 'https://api.dicebear.com/6.x/bottts/svg?seed=LeilaGomez12', 'Brazil', 'Community manager and social strategist with a global mindset.', 'Social Media,Content,Marketing', 150, 0, NULL, NULL, '2026-04-17 22:22:58', '2026-04-18 14:21:28', 'Pinheiros, São Paulo, Brazil', -23.5614, -46.6786),
(13, 'Arjun', 'Patel', 'arjun.patel13@example.com', '$2b$10$w6Bihm5Br4Rnen6KKrwOmezPtb63wQ1ao8WcCvRsEoi5YHRU5PPPi', '+91 22 1234 5678', 'user', 1, 'https://api.dicebear.com/6.x/bottts/svg?seed=ArjunPatel13', 'India', 'Mobile app developer who builds modern native experiences.', 'Android,iOS,Flutter', 200, 0, NULL, NULL, '2026-04-17 22:22:58', '2026-04-18 14:21:28', 'Bandra, Mumbai, India', 19.0596, 72.8295),
(14, 'Zara', 'Morrison', 'zara.morrison14@example.com', '$2b$10$w6Bihm5Br4Rnen6KKrwOmezPtb63wQ1ao8WcCvRsEoi5YHRU5PPPi', '+1 416 555 0198', 'user', 1, 'https://api.dicebear.com/6.x/bottts/svg?seed=ZaraMorrison14', 'Canada', 'Data analyst focused on product growth and metrics.', 'SQL,Python,Data Analysis', 134, 0, NULL, NULL, '2026-04-17 22:22:58', '2026-04-18 14:21:28', 'Plateau-Mont-Royal, Montreal, Canada', 45.5276, -73.5818),
(15, 'Omar', 'Hassan', 'omar.hassan15@example.com', '$2b$10$w6Bihm5Br4Rnen6KKrwOmezPtb63wQ1ao8WcCvRsEoi5YHRU5PPPi', '+20 2 2345 6789', 'user', 1, 'https://api.dicebear.com/9.x/avataaars/svg?seed=profile-header-random-Omar-Hassan-1776518581546', 'Egypt', 'Freelance photographer and digital storyteller.', 'Photography,Editing,Storytelling', 98, 0, '2026-04-18 14:22:49', NULL, '2026-04-17 22:22:58', '2026-04-18 14:23:01', 'Zamalek, Cairo, Egypt', 30.0444, 31.2357),
(16, 'Sofia', 'Lund', 'sofia.lund16@example.com', '$2b$10$w6Bihm5Br4Rnen6KKrwOmezPtb63wQ1ao8WcCvRsEoi5YHRU5PPPi', '+46 8 123 456 79', 'user', 1, 'https://api.dicebear.com/6.x/bottts/svg?seed=SofiaLund16', 'Sweden', 'Frontend developer with a love for animations and interfaces.', 'HTML,CSS,Vue', 165, 0, NULL, NULL, '2026-04-17 22:22:58', '2026-04-18 14:21:28', 'Östermalm, Stockholm, Sweden', 59.3293, 18.0686),
(17, 'Mateo', 'Wilson', 'mateo.wilson17@example.com', '$2b$10$w6Bihm5Br4Rnen6KKrwOmezPtb63wQ1ao8WcCvRsEoi5YHRU5PPPi', '+1 305 555 0147', 'user', 1, 'https://api.dicebear.com/9.x/avataaars/svg?seed=profile-header-random-Mateo-Wilson-1776461132890', 'United States', 'Digital marketer optimizing campaigns for tech startups.', 'SEO,Ads,Analytics', 111, 0, '2026-04-17 22:25:27', NULL, '2026-04-17 22:22:58', '2026-04-18 14:21:28', 'Miami Beach, Florida, USA', 25.7907, -80.13),
(18, 'Clara', 'Becker', 'clara.becker18@example.com', '$2b$10$w6Bihm5Br4Rnen6KKrwOmezPtb63wQ1ao8WcCvRsEoi5YHRU5PPPi', '+49 30 1234 5678', 'user', 1, 'https://api.dicebear.com/6.x/bottts/svg?seed=ClaraBecker18', 'Germany', 'Backend engineer building scalable APIs and services.', 'Node.js,MySQL,APIs', 142, 0, NULL, NULL, '2026-04-17 22:22:58', '2026-04-18 14:21:28', 'Kreuzberg, Berlin, Germany', 52.52, 13.405),
(19, 'Nadya', 'Kuznetsova', 'nadya.kuznetsova19@example.com', '$2b$10$w6Bihm5Br4Rnen6KKrwOmezPtb63wQ1ao8WcCvRsEoi5YHRU5PPPi', '+7 495 123-45-67', 'user', 1, 'https://api.dicebear.com/6.x/bottts/svg?seed=NadyaKuznetsova19', 'Russia', 'QA engineer with an eye for reliability and automation.', 'Testing,Automation,Selenium', 128, 0, NULL, NULL, '2026-04-17 22:22:58', '2026-04-18 14:21:28', 'Arbat, Moscow, Russia', 55.7558, 37.6173),
(20, 'Liam', 'O’Connor', 'liam.oconnor20@example.com', '$2b$10$w6Bihm5Br4Rnen6KKrwOmezPtb63wQ1ao8WcCvRsEoi5YHRU5PPPi', '+353 1 234 5678', 'user', 1, 'https://api.dicebear.com/6.x/bottts/svg?seed=LiamOConnor20', 'Ireland', 'Startup founder focused on smart workflows.', 'Strategy,Operations,Product', 179, 0, NULL, NULL, '2026-04-17 22:22:58', '2026-04-18 14:21:28', 'Temple Bar, Dublin, Ireland', 53.3498, -6.2603),
(21, 'Ava', 'Martinez', 'ava.martinez21@example.com', '$2b$10$w6Bihm5Br4Rnen6KKrwOmezPtb63wQ1ao8WcCvRsEoi5YHRU5PPPi', '+52 55 1234 5678', 'user', 1, 'https://api.dicebear.com/6.x/bottts/svg?seed=AvaMartinez21', 'Mexico', 'UX writer shaping clear and friendly product copy.', 'Copywriting,UX,Research', 140, 0, NULL, NULL, '2026-04-17 22:22:58', '2026-04-18 14:21:28', 'La Condesa, Mexico City, Mexico', 19.4326, -99.1332),
(22, 'Ethan', 'Jones', 'ethan.jones22@example.com', '$2b$10$w6Bihm5Br4Rnen6KKrwOmezPtb63wQ1ao8WcCvRsEoi5YHRU5PPPi', '+1 212 555 0123', 'user', 1, 'https://api.dicebear.com/6.x/bottts/svg?seed=EthanJones22', 'United States', 'Cybersecurity analyst protecting apps and data.', 'Security,Python,AWS', 158, 0, NULL, NULL, '2026-04-17 22:22:58', '2026-04-18 14:21:28', 'Chelsea, New York, USA', 40.7465, -74.0014),
(23, 'Maya', 'Mbatha', 'maya.mbatha23@example.com', '$2b$10$w6Bihm5Br4Rnen6KKrwOmezPtb63wQ1ao8WcCvRsEoi5YHRU5PPPi', '+27 21 123 4567', 'user', 1, 'https://api.dicebear.com/6.x/bottts/svg?seed=MayaMbatha23', 'South Africa', 'Creative director blending art and UX design.', 'Branding,Illustration,UX', 132, 0, NULL, NULL, '2026-04-17 22:22:58', '2026-04-18 14:21:28', 'Sea Point, Cape Town, South Africa', -33.9249, 18.4241),
(24, 'Hana', 'Al-Farsi', 'hana.alfarsi24@example.com', '$2b$10$w6Bihm5Br4Rnen6KKrwOmezPtb63wQ1ao8WcCvRsEoi5YHRU5PPPi', '+971 4 123 4567', 'user', 1, 'https://api.dicebear.com/6.x/bottts/svg?seed=HanaAlFarsi24', 'United Arab Emirates', 'Cloud engineer working on scalable deployments.', 'DevOps,AWS,Kubernetes', 190, 0, NULL, NULL, '2026-04-17 22:22:58', '2026-04-18 14:21:28', 'Jumeirah, Dubai, UAE', 25.2046, 55.2384),
(25, 'Luca', 'Ricci', 'luca.ricci25@example.com', '$2b$10$w6Bihm5Br4Rnen6KKrwOmezPtb63wQ1ao8WcCvRsEoi5YHRU5PPPi', '+39 06 1234 5678', 'user', 1, 'https://api.dicebear.com/6.x/bottts/svg?seed=LucaRicci25', 'Italy', 'Backend developer and open-source contributor.', 'PHP,Laravel,MySQL', 168, 0, NULL, NULL, '2026-04-17 22:22:58', '2026-04-18 14:21:28', 'Trastevere, Rome, Italy', 41.9028, 12.4964),
(26, 'Sofia', 'Rojas', 'sofia.rojas26@example.com', '$2b$10$w6Bihm5Br4Rnen6KKrwOmezPtb63wQ1ao8WcCvRsEoi5YHRU5PPPi', '+56 2 1234 5678', 'user', 1, 'https://api.dicebear.com/9.x/avataaars/svg?seed=profile-header-random-Sofia-Rojas-1776539481409', 'Chile', 'Community organizer building local tech events.', 'Events,Networking,Community', 105, 0, '2026-04-18 20:11:09', NULL, '2026-04-17 22:22:58', '2026-04-18 20:11:21', 'Bellavista, Santiago, Chile', -33.4489, -70.6693),
(27, 'Aiden', 'Brooks', 'aiden.brooks27@example.com', '$2b$10$w6Bihm5Br4Rnen6KKrwOmezPtb63wQ1ao8WcCvRsEoi5YHRU5PPPi', '+61 2 1234 5678', 'user', 1, 'https://api.dicebear.com/6.x/bottts/svg?seed=AidenBrooks27', 'Australia', 'Game developer building playful interactive experiences.', 'Unity,C#,Game Design', 144, 0, NULL, NULL, '2026-04-17 22:22:58', '2026-04-18 14:21:28', 'Surry Hills, Sydney, Australia', -33.8688, 151.2093),
(28, 'Ines', 'Silva', 'ines.silva28@example.com', '$2b$10$w6Bihm5Br4Rnen6KKrwOmezPtb63wQ1ao8WcCvRsEoi5YHRU5PPPi', '+351 21 123 4567', 'user', 1, 'https://api.dicebear.com/6.x/bottts/svg?seed=InesSilva28', 'Portugal', 'Front-end engineer with a focus on responsive design.', 'CSS,React,Accessibility', 117, 0, NULL, NULL, '2026-04-17 22:22:58', '2026-04-18 14:21:28', 'Baixa, Lisbon, Portugal', 38.7223, -9.1393),
(29, 'Diego', 'Pereira', 'diego.pereira29@example.com', '$2b$10$w6Bihm5Br4Rnen6KKrwOmezPtb63wQ1ao8WcCvRsEoi5YHRU5PPPi', '+51 1 123 4567', 'user', 1, 'https://api.dicebear.com/6.x/bottts/svg?seed=DiegoPereira29', 'Peru', 'Growth hacker focused on mobile-first acquisition.', 'Growth,Analytics,Mobile', 125, 0, NULL, NULL, '2026-04-17 22:22:58', '2026-04-18 14:21:28', 'Barranco, Lima, Peru', -12.0464, -77.0428),
(30, 'Sofia', 'Fernandez', 'sofia.fernandez@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+34 612 345 678', 'freelancer', 1, 'https://api.dicebear.com/9.x/adventurer/svg?seed=sofia-fernandez', 'Spain', 'UI/UX designer passionate about accessible design.', 'Figma,Sketch,CSS,React', 180, 0, NULL, NULL, '2026-04-18 14:20:55', '2026-04-18 14:20:55', 'Madrid, Spain', 40.4168, -3.7038),
(31, 'Liam', 'O\'Brien', 'liam.obrien@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+353 87 123 4567', 'freelancer', 1, 'https://api.dicebear.com/9.x/adventurer/svg?seed=liam-obrien', 'Ireland', 'Full-stack developer focused on fintech solutions.', 'Node.js,TypeScript,PostgreSQL', 250, 0, NULL, NULL, '2026-04-18 14:20:55', '2026-04-18 14:20:55', 'Dublin, Ireland', 53.3498, -6.2603),
(32, 'Yuki', 'Tanaka', 'yuki.tanaka@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+81 90 1234 5678', 'client', 1, 'https://api.dicebear.com/9.x/adventurer/svg?seed=yuki-tanaka', 'Japan', 'Startup founder building the next travel tech.', 'Product Management,Strategy', 420, 0, NULL, NULL, '2026-04-18 14:20:55', '2026-04-18 14:20:55', 'Tokyo, Japan', 35.6762, 139.6503),
(33, 'Amara', 'Diallo', 'amara.diallo@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+221 77 123 4567', 'freelancer', 1, 'https://api.dicebear.com/9.x/adventurer/svg?seed=amara-diallo', 'Senegal', 'Mobile developer specializing in offline-first apps.', 'Flutter,Dart,Firebase', 190, 0, NULL, NULL, '2026-04-18 14:20:55', '2026-04-18 14:20:55', 'Dakar, Senegal', 14.7167, -17.4677),
(34, 'Max', 'Müller', 'max.muller@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+49 171 234 5678', 'freelancer', 1, 'https://api.dicebear.com/9.x/adventurer/svg?seed=max-muller', 'Germany', 'DevOps engineer and cloud architect.', 'AWS,Docker,Kubernetes,Terraform', 310, 0, NULL, NULL, '2026-04-18 14:20:55', '2026-04-18 14:20:55', 'Berlin, Germany', 52.52, 13.405),
(35, 'Priya', 'Sharma', 'priya.sharma@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+91 98765 43210', 'client', 1, 'https://api.dicebear.com/9.x/adventurer/svg?seed=priya-sharma', 'India', 'Tech recruiter connecting global talent.', 'Hiring,HR Tech,Recruiting', 275, 0, NULL, NULL, '2026-04-18 14:20:55', '2026-04-18 14:20:55', 'Mumbai, India', 19.076, 72.8777),
(36, 'Lucas', 'Santos', 'lucas.santos@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+55 11 98765 4321', 'freelancer', 1, 'https://api.dicebear.com/9.x/adventurer/svg?seed=lucas-santos', 'Brazil', 'Backend developer and open-source contributor.', 'Go,Rust,PostgreSQL', 340, 0, NULL, NULL, '2026-04-18 14:20:55', '2026-04-18 14:20:55', 'São Paulo, Brazil', -23.5505, -46.6333),
(37, 'Emma', 'Johansson', 'emma.johansson@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+46 70 123 4567', 'freelancer', 1, 'https://api.dicebear.com/9.x/adventurer/svg?seed=emma-johansson', 'Sweden', 'Data scientist with ML expertise.', 'Python,TensorFlow,Pandas,SQL', 290, 0, NULL, NULL, '2026-04-18 14:20:55', '2026-04-18 14:20:55', 'Stockholm, Sweden', 59.3293, 18.0686),
(38, 'Omar', 'Hassan', 'omar.hassan@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+20 100 123 4567', 'freelancer', 1, 'https://api.dicebear.com/9.x/adventurer/svg?seed=omar-hassan', 'Egypt', 'Graphics designer and brand identity specialist.', 'Photoshop,Illustrator,Branding', 215, 0, NULL, NULL, '2026-04-18 14:20:55', '2026-04-18 14:20:55', 'Cairo, Egypt', 30.0444, 31.2357),
(39, 'Chloe', 'Dupont', 'chloe.dupont@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+33 6 12 34 56 78', 'client', 1, 'https://api.dicebear.com/9.x/adventurer/svg?seed=chloe-dupont', 'France', 'Creative director at a Paris agency.', 'Branding,Motion Design,Leadership', 380, 0, NULL, NULL, '2026-04-18 14:20:55', '2026-04-18 14:20:55', 'Paris, France', 48.8566, 2.3522),
(40, 'Jin', 'Wei', 'jin.wei@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+86 138 1234 5678', 'freelancer', 1, 'https://api.dicebear.com/9.x/adventurer/svg?seed=jin-wei', 'China', 'AI/ML researcher and Python developer.', 'PyTorch,NLP,Computer Vision', 410, 0, NULL, NULL, '2026-04-18 14:20:55', '2026-04-18 14:20:55', 'Shanghai, China', 31.2304, 121.4737),
(41, 'Aisha', 'Mohammed', 'aisha.mohammed@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+971 50 123 4567', 'client', 1, 'https://api.dicebear.com/9.x/adventurer/svg?seed=aisha-mohammed', 'UAE', 'Venture partner investing in MENA startups.', 'Investing,Strategy,Fintech', 350, 0, NULL, NULL, '2026-04-18 14:20:55', '2026-04-18 14:20:55', 'Dubai, UAE', 25.2048, 55.2708),
(42, 'Noah', 'Williams', 'noah.williams@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+1 415 555 0123', 'freelancer', 1, 'https://api.dicebear.com/9.x/adventurer/svg?seed=noah-williams', 'United States', 'iOS and SwiftUI developer.', 'Swift,SwiftUI,Xcode,UIKit', 280, 0, NULL, NULL, '2026-04-18 14:20:55', '2026-04-18 14:20:55', 'San Francisco, USA', 37.7749, -122.4194),
(43, 'Maria', 'Rossi', 'maria.rossi@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+39 333 123 4567', 'freelancer', 1, 'https://api.dicebear.com/9.x/adventurer/svg?seed=maria-rossi', 'Italy', 'Illustrator and visual storyteller.', 'Procreate,Figma,After Effects', 230, 0, NULL, NULL, '2026-04-18 14:20:55', '2026-04-18 14:20:55', 'Rome, Italy', 41.9028, 12.4964),
(44, 'Kwame', 'Asante', 'kwame.asante@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+233 24 123 4567', 'freelancer', 1, 'https://api.dicebear.com/9.x/adventurer/svg?seed=kwame-asante', 'Ghana', 'Cybersecurity analyst and ethical hacker.', 'Pentesting,OSCP,Burp Suite', 260, 0, NULL, NULL, '2026-04-18 14:20:55', '2026-04-18 14:20:55', 'Accra, Ghana', 5.6037, -0.187),
(45, 'Hana', 'Kim', 'hana.kim@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+82 10 1234 5678', 'client', 1, 'https://api.dicebear.com/9.x/adventurer/svg?seed=hana-kim', 'South Korea', 'Product manager at a K-pop tech startup.', 'Product,Agile,Data Analytics', 330, 0, NULL, NULL, '2026-04-18 14:20:55', '2026-04-18 14:20:55', 'Seoul, South Korea', 37.5665, 126.978),
(46, 'Carlos', 'Mendez', 'carlos.mendez@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+52 55 1234 5678', 'freelancer', 1, 'https://api.dicebear.com/9.x/adventurer/svg?seed=carlos-mendez', 'Mexico', 'Game developer and Unity specialist.', 'Unity,C#,Blender,3D', 200, 0, NULL, NULL, '2026-04-18 14:20:55', '2026-04-18 14:20:55', 'Mexico City, Mexico', 19.4326, -99.1332),
(47, 'Fatima', 'Ben Ali', 'fatima.benali@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+216 55 123 456', 'freelancer', 1, 'https://api.dicebear.com/9.x/adventurer/svg?seed=fatima-benali', 'Tunisia', 'Frontend wizard and accessibility champion.', 'React,Vue,A11y,CSS', 270, 0, NULL, NULL, '2026-04-18 14:20:55', '2026-04-18 14:20:55', 'Sousse, Tunisia', 35.8256, 10.6369),
(48, 'Alex', 'Petrov', 'alex.petrov@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+7 916 123 4567', 'freelancer', 1, 'https://api.dicebear.com/9.x/adventurer/svg?seed=alex-petrov', 'Russia', 'Blockchain developer and DeFi enthusiast.', 'Solidity,Web3,Ethereum', 360, 0, NULL, NULL, '2026-04-18 14:20:55', '2026-04-18 14:20:55', 'Moscow, Russia', 55.7558, 37.6173),
(49, 'Olivia', 'Chen', 'olivia.chen@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+61 412 345 678', 'client', 1, 'https://api.dicebear.com/9.x/adventurer/svg?seed=olivia-chen', 'Australia', 'COO scaling remote-first companies.', 'Operations,HR,Remote Work', 400, 0, NULL, NULL, '2026-04-18 14:20:55', '2026-04-18 14:20:55', 'Sydney, Australia', -33.8688, 151.2093);

-- --------------------------------------------------------

--
-- Table structure for table `user_delete_requests`
--

CREATE TABLE `user_delete_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `admin_note` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_delete_requests`
--

INSERT INTO `user_delete_requests` (`id`, `user_id`, `requested_by`, `reason`, `status`, `admin_note`, `reviewed_by`, `reviewed_at`, `created_at`, `updated_at`) VALUES
(1, 7, 7, 'aa', 'approved', NULL, 1, '2026-04-15 10:07:29', '2026-04-15 10:07:14', '2026-04-15 10:07:29'),
(2, 9, 9, 'GGGGGGG', 'canceled', NULL, NULL, '2026-04-15 10:51:41', '2026-04-15 10:51:27', '2026-04-15 10:51:41');

-- --------------------------------------------------------

--
-- Table structure for table `user_signin_history`
--

CREATE TABLE `user_signin_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `signed_in_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `device_type` varchar(20) DEFAULT NULL,
  `os` varchar(60) DEFAULT NULL,
  `browser` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_signin_history`
--

INSERT INTO `user_signin_history` (`id`, `user_id`, `signed_in_at`, `ip_address`, `user_agent`, `device_type`, `os`, `browser`) VALUES
(1, 3, '2026-04-15 09:25:00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'Desktop', 'Windows 10', 'Chrome 147.0'),
(2, 4, '2026-04-15 09:29:27', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'Desktop', 'Windows 10', 'Chrome 147.0'),
(3, 6, '2026-04-15 09:52:26', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'Desktop', 'Windows 10', 'Chrome 147.0'),
(4, 6, '2026-04-15 10:00:21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'Desktop', 'Windows 10', 'Chrome 147.0'),
(5, 7, '2026-04-15 10:06:16', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'Desktop', 'Windows 10', 'Chrome 147.0'),
(6, 8, '2026-04-15 10:23:23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'Desktop', 'Windows 10', 'Chrome 147.0'),
(7, 9, '2026-04-15 10:44:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'Desktop', 'Windows 10', 'Chrome 147.0'),
(8, 17, '2026-04-17 22:25:27', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'Desktop', 'Windows 10', 'Chrome 147.0'),
(9, 15, '2026-04-18 14:22:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'Desktop', 'Windows 10', 'Chrome 147.0'),
(10, 26, '2026-04-18 20:11:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'Desktop', 'Windows 10', 'Chrome 147.0'),
(11, 3, '2026-04-18 22:02:08', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'Desktop', 'Windows 10', 'Chrome 147.0');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `call_sessions`
--
ALTER TABLE `call_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_call_sessions_thread` (`thread_type`,`thread_id`),
  ADD KEY `idx_call_sessions_callee` (`callee_id`,`status`),
  ADD KEY `idx_call_sessions_caller` (`caller_id`,`status`);

--
-- Indexes for table `call_signals`
--
ALTER TABLE `call_signals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_call_signals_session` (`session_id`,`id`);

--
-- Indexes for table `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contracts_offer_id` (`job_offer_id`),
  ADD KEY `idx_contracts_client_id` (`client_id`),
  ADD KEY `idx_contracts_freelancer_id` (`freelancer_id`),
  ADD KEY `idx_contracts_status` (`status`),
  ADD KEY `fk_contracts_created_by` (`created_by_client_id`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `conversation_members`
--
ALTER TABLE `conversation_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_conv_user` (`conversation_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `friends`
--
ALTER TABLE `friends`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`friend_id`),
  ADD KEY `friend_id` (`friend_id`),
  ADD KEY `idx_friends_user_one` (`user_one_id`),
  ADD KEY `idx_friends_user_two` (`user_two_id`);

--
-- Indexes for table `friend_requests`
--
ALTER TABLE `friend_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_friend_requests_sender_receiver_status` (`sender_id`,`receiver_id`,`status`),
  ADD KEY `idx_friend_requests_receiver_status` (`receiver_id`,`status`),
  ADD KEY `idx_friend_requests_sender_status` (`sender_id`,`status`),
  ADD KEY `idx_friend_requests_created_at` (`created_at`);

--
-- Indexes for table `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_groups_created_by` (`created_by`);

--
-- Indexes for table `group_chats`
--
ALTER TABLE `group_chats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `fk_group_conversation` (`conversation_id`);

--
-- Indexes for table `group_chat_members`
--
ALTER TABLE `group_chat_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `group_id` (`group_id`,`user_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_group_chat_members_group_chat_id` (`group_chat_id`),
  ADD KEY `idx_group_chat_members_left_at` (`left_at`);

--
-- Indexes for table `group_members`
--
ALTER TABLE `group_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_group_members_pair` (`group_id`,`user_id`),
  ADD KEY `idx_group_members_user` (`user_id`);

--
-- Indexes for table `group_reports`
--
ALTER TABLE `group_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_group_reports_group` (`group_chat_id`,`status`),
  ADD KEY `idx_group_reports_reporter` (`reporter_id`,`created_at`);

--
-- Indexes for table `job_offers`
--
ALTER TABLE `job_offers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_job_offers_client_id` (`client_id`),
  ADD KEY `idx_job_offers_status` (`status`);

--
-- Indexes for table `job_offer_applications`
--
ALTER TABLE `job_offer_applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_offer_freelancer` (`job_offer_id`,`freelancer_id`),
  ADD KEY `idx_joa_offer_id` (`job_offer_id`),
  ADD KEY `idx_joa_freelancer_id` (`freelancer_id`),
  ADD KEY `idx_joa_status` (`status`);

--
-- Indexes for table `linked_accounts`
--
ALTER TABLE `linked_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_linked_accounts_user_platform` (`user_id`,`platform`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `idx_messages_sender_created` (`sender_id`,`created_at`),
  ADD KEY `idx_messages_conversation_created` (`conversation_id`,`created_at`),
  ADD KEY `idx_messages_private_conversation_created` (`private_conversation_id`,`created_at`),
  ADD KEY `idx_messages_group_chat_created` (`group_chat_id`,`created_at`);

--
-- Indexes for table `message_attachments`
--
ALTER TABLE `message_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `message_id` (`message_id`);

--
-- Indexes for table `message_reactions`
--
ALTER TABLE `message_reactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_message_reactions_unique` (`message_id`,`user_id`,`reaction`),
  ADD KEY `idx_message_reactions_message` (`message_id`,`reaction`),
  ADD KEY `fk_message_reactions_user` (`user_id`);

--
-- Indexes for table `message_reads`
--
ALTER TABLE `message_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_message_reads_unique` (`message_id`,`user_id`),
  ADD KEY `idx_message_reads_user_id` (`user_id`,`read_at`);

--
-- Indexes for table `private_conversations`
--
ALTER TABLE `private_conversations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_private_conversations_pair` (`user_one_id`,`user_two_id`),
  ADD KEY `idx_private_conversations_last_message_at` (`last_message_at`),
  ADD KEY `fk_private_conversations_user_two` (`user_two_id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_projects_owner_id` (`owner_id`),
  ADD KEY `idx_projects_status` (`status`);

--
-- Indexes for table `stories`
--
ALTER TABLE `stories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_stories_user_expire` (`user_id`,`expires_at`),
  ADD KEY `idx_stories_author_expire` (`user_id`,`expires_at`);

--
-- Indexes for table `story_media`
--
ALTER TABLE `story_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_story_media_story` (`story_id`);

--
-- Indexes for table `story_views`
--
ALTER TABLE `story_views`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_story_view` (`story_id`,`viewer_id`),
  ADD KEY `fk_story_view_viewer` (`viewer_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_delete_requests`
--
ALTER TABLE `user_delete_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_udr_user_id` (`user_id`),
  ADD KEY `idx_udr_status` (`status`);

--
-- Indexes for table `user_signin_history`
--
ALTER TABLE `user_signin_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_signin_user` (`user_id`),
  ADD KEY `idx_user_signin_at` (`signed_in_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `call_sessions`
--
ALTER TABLE `call_sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `call_signals`
--
ALTER TABLE `call_signals`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `conversation_members`
--
ALTER TABLE `conversation_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `friends`
--
ALTER TABLE `friends`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `friend_requests`
--
ALTER TABLE `friend_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `groups`
--
ALTER TABLE `groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `group_chats`
--
ALTER TABLE `group_chats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `group_chat_members`
--
ALTER TABLE `group_chat_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `group_members`
--
ALTER TABLE `group_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `group_reports`
--
ALTER TABLE `group_reports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `job_offers`
--
ALTER TABLE `job_offers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `job_offer_applications`
--
ALTER TABLE `job_offer_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `linked_accounts`
--
ALTER TABLE `linked_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `message_attachments`
--
ALTER TABLE `message_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `message_reactions`
--
ALTER TABLE `message_reactions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `message_reads`
--
ALTER TABLE `message_reads`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `private_conversations`
--
ALTER TABLE `private_conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stories`
--
ALTER TABLE `stories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `story_media`
--
ALTER TABLE `story_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `story_views`
--
ALTER TABLE `story_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `user_delete_requests`
--
ALTER TABLE `user_delete_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user_signin_history`
--
ALTER TABLE `user_signin_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `contracts`
--
ALTER TABLE `contracts`
  ADD CONSTRAINT `fk_contracts_client` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_contracts_created_by` FOREIGN KEY (`created_by_client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_contracts_freelancer` FOREIGN KEY (`freelancer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_contracts_offer` FOREIGN KEY (`job_offer_id`) REFERENCES `job_offers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `fk_conversations_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `conversation_members`
--
ALTER TABLE `conversation_members`
  ADD CONSTRAINT `fk_conv_member_conv` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_conv_member_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `friends`
--
ALTER TABLE `friends`
  ADD CONSTRAINT `friends_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `friends_ibfk_2` FOREIGN KEY (`friend_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `friend_requests`
--
ALTER TABLE `friend_requests`
  ADD CONSTRAINT `fk_friend_requests_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_friend_requests_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `groups`
--
ALTER TABLE `groups`
  ADD CONSTRAINT `fk_groups_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `group_chats`
--
ALTER TABLE `group_chats`
  ADD CONSTRAINT `fk_group_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_chats_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `group_chat_members`
--
ALTER TABLE `group_chat_members`
  ADD CONSTRAINT `group_chat_members_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `group_chats` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_chat_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `group_members`
--
ALTER TABLE `group_members`
  ADD CONSTRAINT `fk_group_members_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_group_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `job_offers`
--
ALTER TABLE `job_offers`
  ADD CONSTRAINT `fk_job_offers_client` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `job_offer_applications`
--
ALTER TABLE `job_offer_applications`
  ADD CONSTRAINT `fk_joa_freelancer` FOREIGN KEY (`freelancer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_joa_offer` FOREIGN KEY (`job_offer_id`) REFERENCES `job_offers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `linked_accounts`
--
ALTER TABLE `linked_accounts`
  ADD CONSTRAINT `linked_accounts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_conv` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`group_id`) REFERENCES `group_chats` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `message_attachments`
--
ALTER TABLE `message_attachments`
  ADD CONSTRAINT `fk_msg_attach_msg` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `message_reactions`
--
ALTER TABLE `message_reactions`
  ADD CONSTRAINT `fk_message_reactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `message_reads`
--
ALTER TABLE `message_reads`
  ADD CONSTRAINT `fk_message_reads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `private_conversations`
--
ALTER TABLE `private_conversations`
  ADD CONSTRAINT `fk_private_conversations_user_one` FOREIGN KEY (`user_one_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_private_conversations_user_two` FOREIGN KEY (`user_two_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `fk_projects_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stories`
--
ALTER TABLE `stories`
  ADD CONSTRAINT `stories_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `story_media`
--
ALTER TABLE `story_media`
  ADD CONSTRAINT `fk_story_media_story` FOREIGN KEY (`story_id`) REFERENCES `stories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `story_views`
--
ALTER TABLE `story_views`
  ADD CONSTRAINT `fk_story_view_story` FOREIGN KEY (`story_id`) REFERENCES `stories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_story_view_viewer` FOREIGN KEY (`viewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
